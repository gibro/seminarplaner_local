<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $stringmanager = get_string_manager();
    $reviewrequeststitle = $stringmanager->string_exists('reviewrequestspage', 'local_konzeptgenerator')
        ? get_string('reviewrequestspage', 'local_konzeptgenerator')
        : 'Review requests for global method sets';

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_konzeptgenerator_manage',
        get_string('manageglobalsets', 'local_konzeptgenerator'),
        new moodle_url('/local/konzeptgenerator/manage.php')
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_konzeptgenerator_reviewrequests',
        $reviewrequeststitle,
        new moodle_url('/local/konzeptgenerator/reviewrequests.php')
    ));
}
