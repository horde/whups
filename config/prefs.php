<?php
/**
 * See horde/config/prefs.php for documentation on the structure of this file.
 *
 * IMPORTANT: Local overrides should be placed in pref.local.php, or
 * prefs-servername.php if the 'vhosts' setting has been enabled in Horde's
 * configuration.
 */

$prefGroups['display'] = array(
    'column' => _("General Preferences"),
    'label' => _("Display Preferences"),
    'desc' => _("Change display preferences such as how search results are sorted."),
    'members' => array(
        'sortby', 'sortdir', 'comment_sort_dir', 'whups_default_view',
        'summary_show_requested', 'summary_show_ticket_numbers',
        'report_time_format', 'autolink_tickets'
    )
);

$prefGroups['notification'] = array(
    'column' => _("General Preferences"),
    'label' => _("Notification Preferences"),
    'desc' => _("Change preferences for email notifications of ticket activity."),
    'members' => array('email_others_only', 'email_comments_only'));

$prefGroups['addressbooks'] = array(
    'column' => _("General Preferences"),
    'label' => _("Address Books"),
    'desc' => _("Select address book sources for adding and searching for addresses."),
    'members' => array('sourceselect'),
);


// the layout of the bugs portal.
$_prefs['mybugs_layout'] = array(
    'value' => 'a:0:{}'
);

// user preferred sorting column
$_prefs['sortby'] = array(
    'value' => 'id',
    'type' => 'enum',
    'enum' => array(
        'id' => _("Id"),
        'summary' => _("Summary"),
        'state_name' => _("State"),
        'type_name'     => _("Type"),
        'priority_name' => _("Priority"),
        'queue_name' => _("Queue"),
        'version_name' => _("Version"),
        'timestamp' => _("Created"),
        'date_assigned' => _("Assigned"),
        'date_resolved' => _("Resolved")
    ),
    'desc' => _("Default sorting criteria:")
);

// user preferred sorting direction
$_prefs['sortdir'] = array(
    'value' => 0,
    'type' => 'enum',
    'enum' => array(
        0 => _("Ascending"),
        1 => _("Descending")
    ),
    'desc' => _("Default sorting direction:")
);

// default view
$_prefs['whups_default_view'] = array(
    'value' => 'mybugs',
    'type' => 'enum',
    'enum' => array(
        'mybugs' => _("My Tickets"),
        'search' => _("Search Tickets"),
        'ticket/create' => _("Create Ticket")
    ),
    'desc' => _("Select the view to display after login:")
);

// show requested tickets in the horde summary?
$_prefs['summary_show_requested'] = array(
    'value' => 1,
    'type' => 'checkbox',
    'desc' => _("Show tickets you have requested in the summary view?"),
);

// show ticket ids in the horde summary?
$_prefs['summary_show_ticket_numbers'] = array(
    'value' => 1,
    'type' => 'checkbox',
    'desc' => _("Show ticket IDs in the summary view?"),
);

// Allow custom time/date formats in reports
$_prefs['report_time_format'] = array(
    'value' => '%m/%d/%y',
    'type' => 'enum',
    'enum' => array(
        '%a %d %B' => _("Weekday Day Month"),
        '%c' => _("Weekday Day Month HH:MM:SS TZ"),
        '%m/%d/%y' => _("MM/DD/YY"),
        '%m/%d/%y %H:%M:%S' => _("MM/DD/YY HH:MM:SS"),
        '%d/%m/%y' => _("DD/MM/YY"),
        '%d/%m/%y %H:%M:%S' => _("DD/MM/YY HH:MM:SS"),
        '%y/%m/%d' => _("YY/MM/DD"),
        '%y/%m/%d %H:%M:%S' => _("YY/MM/DD HH:MM:SS"),
    ),
    'desc' => _("Date/Time format for search results"),
);

// Skip notification of changes you added?
$_prefs['email_others_only'] = array(
    'value' => 1,
    'type' => 'checkbox',
    'desc' => _("Only notify me of ticket changes from other users?"),
);

// Skip notification without comments?
$_prefs['email_comments_only'] = array(
    'value' => 0,
    'type' => 'checkbox',
    'desc' => _("Only notify me of ticket changes with comments?")
);

// AutoLink to tickets references in comments
$_prefs['autolink_tickets'] = array(
    'value' => 1,
    'type' => 'checkbox',
    'desc' => _("Autolink to other tickets in comments?")
);

// Show ticket comments in ascending or descending order?
$_prefs['comment_sort_dir'] = array(
    'value' => 1,
    'type' => 'enum',
    'enum' => array(
        0 => _("Chronological (oldest first)"),
        1 => _("Most recent first")
    ),
    'desc' => _("Show comments in chronological order, or most recent first?")
);

// address book selection widget
$_prefs['sourceselect'] = array('type' => 'special');

// Address book(s) to use when expanding addresses
// Refer to turba/config/sources.php for possible source values
//
// You can provide default values this way:
//   'value' => json_encode(array('source_one', 'source_two'))

$_prefs['search_sources'] = array(
    'value' => ''
);

// Field(s) to use when expanding addresses
// Refer to turba/config/sources.php for possible source and field values
//
// If you want to provide a default value, this field depends on the
// search_sources preference. For example:
//   'value' => json_encode(array(
//       'source_one' => array('field_one', 'field_two'),
//       'source_two' => array('field_three')
//   ))
// will search the fields 'field_one' and 'field_two' in source_one and
// 'field_three' in source_two.
$_prefs['search_fields'] = array(
    'value' => ''
);