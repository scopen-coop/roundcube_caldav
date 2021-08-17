<p id='loading'> <?php echo $this->gettext("loading") ?></p>
<template id="display" style="display: none">
    <div class="content_invitation">
        <div class="invitation">
        </div>
        <div class="is_on_server">
        </div>
        <div class="content_event">
            <div class="date_container">
                <div class="date_event">
                    <div class="icon_date same_date" class="alone">
                        <div class="d"></div>
                        <div class="m"></div>
                        <div class="h"></div>
                    </div>
                    <div class="icon_date different_date start">
                        <div class="d"></div>
                        <div class="m"></div>
                        <div class="h"></div>
                    </div>
                    <div class="arrow-right different_date"></div>
                    <div class="icon_date different_date end">
                        <div class="d"></div>
                        <div class="m"></div>
                        <div class="h"></div>
                    </div>
                </div>
                <div class="arrow-right new " style="display: none"></div>
                <div class="arrow-down new " style="display: none"></div>
                <div class="new_date_event new" style="display: none">

                    <div class="icon_date same_date" class="alone">
                        <div class="d"></div>
                        <div class="m"></div>
                        <div class="h"></div>
                    </div>
                    <div class="icon_date different_date start">
                        <div class="d"></div>
                        <div class="m"></div>
                        <div class="h"></div>
                    </div>
                    <div class="arrow-right different_date"></div>
                    <div class="icon_date different_date end">
                        <div class="d"></div>
                        <div class="m"></div>
                        <div class="h"></div>
                    </div>
                </div>
            </div>
            <div class="info_ics location_description">
                <p class="location" style="display: none"><b><?php echo $this->gettext("location") ?></b></p>
                <p class="if_new_location new" style="display: none">
                    <b><?php echo $this->gettext("new_location_proposal") ?></b></p>
                <p class="description" style="display: none"><b><?php echo $this->gettext("description") ?></b></p>
                <p class="if_new_description new" style="display: none">
                    <b><?php echo $this->gettext("new_description_proposal") ?></b></p>
            </div>


            <div class="info_ics repeated ">
                <p><b><?php echo $this->gettext("repeated_event"); ?></b></p>
            </div>


            <div class="info_ics attendee">
                <b><?php echo $this->gettext("attendee"); ?></b><br><br>
                <div class='attendee_link'>

                </div>
                <div class="reply_all"></div>
            </div>
            <div class="info_caldav_server">
                <div class="close_meeting previous">
                    <p><b><?php echo $this->gettext('previous_meeting') ?></b></p>
                </div>

                <div class="meeting_collision">
                    <p><?php echo $this->gettext('same_hour_meeting') ?></p>
                </div>


                <div class="close_meeting next">
                    <p><b><?php echo $this->gettext('next_meeting') ?></b></p>
                </div>

            </div>
        </div>
        <div class="if_rescheduled new" style="display:none">
            <p><b><?php echo $this->gettext("if_rescheduled_msg") ?></b></p>
        </div>
        <div class="if_modification new" style="display: none"></div>
        <div class="action_button">
            <form method="post" name="chosen_cal">
                <label class="calendar_choice">
                    <select class="choose_calendar_to_add_event custom-select">
                    </select>
                </label>

            </form>
            <div class="dialog-form" title="<?php echo $this->gettext('reschedule_meeting') ?>">
                <form>
                    <fieldset>
                        <label for="location_input"><?php echo $this->gettext("new_location") ?></label>
                        <input type="text" name="location" class="location_input">
                        <label class="label_popup"
                               for="date_start"><?php echo $this->gettext("new_date_start") ?></label>
                        <input class="date_start" type="date">
                        <label class="label_popup"
                               for="time_start"><?php echo $this->gettext("new_time_start") ?></label>
                        <input class="time_start" type="time">
                        <label class="label_popup"
                               for="date_end"><?php echo $this->gettext("new_date_end") ?></label>
                        <input class="date_end" type="date">
                        <label class="label_popup"
                               for="time_end"><?php echo $this->gettext("new_time_end") ?></label>
                        <input class="time_end" type="time">
                    </fieldset>
                </form>
            </div>

            <button class="action_buttons open_dialog btn btn-secondary"><?php echo $this->gettext('reschedule_meeting') ?></button>
            <button class="action_buttons confirm_button btn btn-secondary"><?php echo $this->gettext('confirm') ?></button>
            <button class="action_buttons tentative_button btn btn-secondary"><?php echo $this->gettext('tentative') ?></button>
            <button class="action_buttons decline_button btn btn-secondary"><?php echo $this->gettext('decline') ?></button>
            <button class="action_buttons update_button btn btn-secondary"><?php echo $this->gettext('update_event') ?></button>
            <button class="action_buttons confirm_button_organizer btn btn-secondary"><?php echo $this->gettext('confirm_modification') ?></button>
            <button class="action_buttons decline_button_organizer btn btn-secondary"><?php echo $this->gettext('decline_modification') ?></button>
        </div>
    </div>
</template>



