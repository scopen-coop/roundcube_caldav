<?php

/**
 * +-----------------------------------------------------------------------+
 * | Localization file of the Roundcube Webmail Enigma plugin              |
 * |                                                                       |
 * | Copyright (C) The Roundcube Dev Team                                  |
 * |                                                                       |
 * | Licensed under the GNU General Public License version 3 or            |
 * | any later version with exceptions for skins & plugins.                |
 * | See the README file for a full license statement.                     |
 * +-----------------------------------------------------------------------+
 */

$labels = array();
$labels['server_caldav'] = 'Configuration of CalDAV server';
$labels['url_base'] = "Specify the url of your calDAV server under the form : \n https://my_caldav_server/remote.php/calendars/my_name";
$labels['login'] = 'Specify your server login';
$labels['password'] = 'Specify your password';
$labels['save_error_msg'] = 'Your information are incorrect or incomplete';
$labels['main_calendar'] = 'Specify your default calendar';
$labels['calendar_choice'] = 'Warning : If you change url / login / password your preferences will be forgotten.\n
Choose your default calendar (the left radio button) and the other calendar you may use (the right checkboxes).';
$labels['same_hour_meeting'] = 'You already have one or several meeting during this period: ';
$labels['connect_error_msg'] = 'Connection with CalDAV server failed';
$labels['attendee'] = 'Attendees: ';
$labels['reply_all'] = 'Reply all';
$labels['previous_meeting'] = 'Previous meeting: ';
$labels['next_meeting'] = 'Next meeting: ';
$labels['invitation'] = 'You just received an invitation';
$labels['all_day'] = 'All day';
$labels['successfully_saved'] = 'This event have been saved in your agenda';
$labels['something_happened'] = "Something happened badly, nothing have been added to your agenda";
$labels['confirm'] = 'Confirm';
$labels['tentative'] = 'Tentative';
$labels['decline'] = "Decline";
$labels['confirmed'] = 'Confirmed';
$labels['tentatived'] = 'Provisionally confirmed';
$labels['declined'] = "Declined";
$labels['confirmed_organizer'] = 'Validated proposal';
$labels['declined_organizer'] = "Refused proposal";
$labels['location'] = "Place: ";
$labels['description'] = "Description: ";
$labels['choose_calendar_to_add'] = "Add this event to :";
$labels['event_not_found_which_calendar_to_add'] = "Event not found in your calendars even if you are organizer, so \n
in which calendar you want this event into ?";
$labels['repeated_event'] = "This event repeat itself through several occurrences";
$labels['use_this_calendar'] = "Use the calendar ";
$labels['make_this_calendar_default1'] = "Select the ";
$labels['make_this_calendar_default2'] = " calendar by default";
$labels['reschedule_meeting'] = "Reschedule this meeting ";
$labels['cancel'] = "Cancel";
$labels['new_date_start'] = 'New begin date for this event';
$labels['new_time_start'] = 'Start times';
$labels['new_time_end'] = 'End times';
$labels['new_date_end'] = 'New end date for this event';
$labels['new_location'] = 'Choose a new location';
$labels['if_rescheduled_msg'] = 'You have rescheduled this event :';
$labels['error_date_inf'] = 'Warning! you can\'t choose a begin date superior to end date';
$labels['error_incomplete_field'] = "Warning  you have not filled in the necessary fields (either the field place or all fields date)";
$labels['modified_event'] = "<b>Warning</b> the displayed event is not the one you received, it is a newer version found on the calendar ";
$labels['missing_summary'] = "Untitled";
$labels['CONFIRMED'] = '[CONFIRMED]: ';
$labels['TENTATIVE'] = '[TENTATIVE]: ';
$labels['CANCELLED'] = "[DECLINED]: ";
$labels['MODIFIED'] = '[MODIFIED]: ';
$labels['DECLINECOUNTER'] = '[DECLINE MODIF]: ';
$labels['all_day'] = 'All Day';
$labels['loading'] = 'Loading...';
$labels['something_happened_while_getting_events'] = "Something happened while getting events on calDAV server";
$labels['invitation_modification'] = "You just receive a modification for your invitation";
$labels['invitation_modification_already_studied'] = "The proposed modifications for this event have already been studied";
$labels['confirm_modification'] = "Confirm modifications";
$labels['decline_modification'] = "Decline modifications";
$labels['update_event'] = "Update Event on server";
$labels['updated_event'] = "Event has been Updated ";
$labels['invitation_accepted'] = " accepted your invitation for the event:  ";
$labels['invitation_declined'] = "  declined your invitation for the event:";
$labels['unknown_reply'] = " reply to your invitation without being in the attendee list";
$labels['new_date_proposal'] = "New schedule proposal: ";
$labels['new_location_proposal'] = "New location proposal: ";
$labels['if_modification'] = "Modifications are displayed green";
$labels['if_no_modification'] = "Changes have already been taken into account";
$labels['invitation_send'] = "You have sent an invitation for: ";
$labels['invitation_decline_modifications1'] = "You have refused ";
$labels['invitation_decline_modifications2'] = "'s proposed changes";
$labels['present_but_older'] = 'You already have this event on your server but under an older version ';
?>
