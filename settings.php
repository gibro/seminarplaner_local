<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $stringmanager = get_string_manager();
    $reviewrequeststitle = $stringmanager->string_exists('reviewrequestspage', 'local_seminarplaner')
        ? get_string('reviewrequestspage', 'local_seminarplaner')
        : 'Review requests for global method sets';

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_seminarplaner_manage',
        get_string('manageglobalsets', 'local_seminarplaner'),
        new moodle_url('/local/seminarplaner/manage.php')
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_seminarplaner_reviewrequests',
        $reviewrequeststitle,
        new moodle_url('/local/seminarplaner/reviewrequests.php')
    ));
}
