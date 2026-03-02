<?php
// This file is part of Moodle - http://moodle.org/

namespace local_konzeptgenerator\local\service;

use coding_exception;
use local_konzeptgenerator\local\repository\methodset_repository;
use local_konzeptgenerator\local\repository\reviewer_repository;
use local_konzeptgenerator\local\repository\workflow_event_repository;
use local_konzeptgenerator\local\workflow\workflow_rules;

defined('MOODLE_INTERNAL') || die();

/**
 * Service for Draft -> Review -> Published transitions.
 */
class workflow_service {
    /** @var methodset_repository */
    private $methodsetrepository;

    /** @var workflow_event_repository */
    private $eventrepository;

    /** @var workflow_rules */
    private $rules;

    /** @var reviewer_repository */
    private $reviewerrepository;

    /**
     * Constructor.
     *
     * @param methodset_repository|null $methodsetrepository Method set repository.
     * @param workflow_event_repository|null $eventrepository Event repository.
     * @param workflow_rules|null $rules Rules helper.
     * @param reviewer_repository|null $reviewerrepository Reviewer repository.
     */
    public function __construct(?methodset_repository $methodsetrepository = null,
        ?workflow_event_repository $eventrepository = null, ?workflow_rules $rules = null,
        ?reviewer_repository $reviewerrepository = null) {
        $this->methodsetrepository = $methodsetrepository ?? new methodset_repository();
        $this->eventrepository = $eventrepository ?? new workflow_event_repository();
        $this->rules = $rules ?? new workflow_rules();
        $this->reviewerrepository = $reviewerrepository ?? new reviewer_repository();
    }

    /**
     * Transition a method set (and optional version).
     *
     * @param int $methodsetid Method set id.
     * @param int|null $versionid Version id.
     * @param string $tostatus Target status.
     * @param int $actorid Actor user id.
     * @param string $comment Optional comment.
     * @return void
     */
    public function transition(int $methodsetid, ?int $versionid, string $tostatus, int $actorid, string $comment = ''): void {
        if ($methodsetid <= 0 || $actorid <= 0 || !$this->rules->is_valid_status($tostatus)) {
            throw new coding_exception('Invalid transition request');
        }

        $methodset = $this->methodsetrepository->get_methodset($methodsetid);
        if (!$methodset) {
            throw new coding_exception('Method set not found');
        }

        $fromstatus = (string)$methodset->status;
        if (!$this->rules->can_transition($fromstatus, $tostatus)) {
            throw new coding_exception('Transition not allowed by workflow rules');
        }

        if ($tostatus === 'review') {
            $reviewerids = $this->reviewerrepository->get_reviewer_userids($methodsetid);
            if (!$reviewerids) {
                throw new coding_exception('At least one reviewer must be assigned before submitting for review');
            }
        }

        $this->methodsetrepository->update_methodset_status($methodsetid, $tostatus, $actorid);

        if ($versionid) {
            $role = 'reviewer';
            if ($tostatus === 'published') {
                $role = 'publisher';
            }
            $this->methodsetrepository->update_version_status($versionid, $tostatus, $actorid, $role);
        }

        if ($tostatus === 'published' && class_exists('\\mod_konzeptgenerator\\local\\service\\methodset_sync_service')) {
            $syncversionid = $versionid ?: (int)($methodset->currentversion ?? 0);
            if ($syncversionid > 0) {
                $syncservice = new \mod_konzeptgenerator\local\service\methodset_sync_service();
                $syncservice->sync_published_methodset($methodsetid, $syncversionid, $actorid);
            }
        }

        $this->eventrepository->create($methodsetid, $versionid, $fromstatus, $tostatus, $comment, $actorid);

        if ($tostatus === 'review') {
            $this->notify_reviewers($methodsetid, $actorid);
        }
    }

    /**
     * Notify all assigned reviewers that a set is ready for review.
     *
     * @param int $methodsetid Method set id.
     * @param int $actorid Submitting user id.
     * @return void
     */
    private function notify_reviewers(int $methodsetid, int $actorid): void {
        global $DB;

        $reviewerids = $this->reviewerrepository->get_reviewer_userids($methodsetid);
        if (!$reviewerids) {
            return;
        }

        $methodset = $this->methodsetrepository->get_methodset($methodsetid);
        if (!$methodset) {
            return;
        }

        $sender = $DB->get_record('user', ['id' => $actorid], '*', IGNORE_MISSING);
        if (!$sender) {
            return;
        }

        list($insql, $params) = $DB->get_in_or_equal($reviewerids, SQL_PARAMS_NAMED);
        $reviewers = $DB->get_records_select('user', "id {$insql} AND deleted = 0 AND suspended = 0", $params);
        if (!$reviewers) {
            return;
        }

        $reviewurl = new \moodle_url('/local/konzeptgenerator/reviewrequests.php');
        $a = (object)[
            'setname' => (string)$methodset->displayname,
            'submitter' => fullname($sender),
            'url' => $reviewurl->out(false),
            'sitename' => format_string((string)get_config('moodle', 'sitename')),
        ];
        $subject = get_string('reviewmail_subject', 'local_konzeptgenerator', $a);
        $text = get_string('reviewmail_body', 'local_konzeptgenerator', $a);
        $html = nl2br(s($text));

        foreach ($reviewers as $reviewer) {
            email_to_user($reviewer, $sender, $subject, $text, $html);
        }
    }
}
