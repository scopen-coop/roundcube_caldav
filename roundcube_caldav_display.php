<div class="conteneur alert ui">
    <template class="display">

        <!--        <div class="uid" hidden>--><?php //echo $used_event->uid ?><!--</div>-->
        <div id="invitation">
            <h3><?php echo $this->gettext('invitation') ?></h3>
        </div>

        <div class="found_advance">
        </div>

        <div class="content_event">
            <div id="date_event">
                <div class="icon_date same_date" id="alone">
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

            <div class="info_ics location_description">
                <p class="location"><b><?php echo $this->gettext("location") ?></b></p>
                <p class="description"><b><?php echo $this->gettext("description") ?></b></p>
            </div>


            <div class="info_ics repeated ">
                <p><b><?php echo $this->gettext("repeated_event"); ?></b></p>
            </div>


            <div class="info_ics attendee">
                <b><?php echo $this->gettext("attendee"); ?></b><br>
                <div class='attendee_link'>

                </div>
                <div class="reply_all"></div>
            </div>
            <div class="info_caldav_server">
                <div class="close_meeting previous">
                    <p><b><?php echo $this->gettext('previous_meeting') ?></b><br/></p>
                </div>
                <br/>
                <div class="meeting_collision">
                    <p><?php echo $this->gettext('same_hour_meeting') ?></p>
                </div>

                <br/>
                <div class="close_meeting next">
                    <p><b><?php echo $this->gettext('next_meeting') ?></b><br/></p>
                </div>

            </div>
        </div>
        <div class="if_rescheduled" style="display:none">
            <p><b><?php echo $this->gettext("if_rescheduled_msg") ?></b></p>
        </div>
        <div class="action_button">
            <form method="post" name="chosen_cal">
                <label>
                    <?php echo $this->gettext("chose_calendar") ?>
                    <select class="choose_calendar_to_add_event custom-select">
                    </select>
                </label>

            </form>
            <div class="dialog-form" title="<?php echo $this->gettext('reschedule_meeting') ?>">
                <form>
                    <fieldset>
                        <label for="location_input"><?php echo $this->gettext("new_location") ?></label>
                        <input type="text" name="location" class="location_input">
                        <label class="label_popup" for="date_start"><?php echo $this->gettext("new_date_start") ?></label>
                        <input class="date_start" type="date">
                        <label class="label_popup" for="time_start"><?php echo $this->gettext("new_time_start") ?></label>
                        <input class="time_start" type="time">
                        <label class="label_popup" for="date_end"><?php echo $this->gettext("new_date_end") ?></label>
                        <input class="date_end" type="date" >
                        <label class="label_popup" for="time_end"><?php echo $this->gettext("new_time_end") ?></label>
                        <input class="time_end" type="time">
                    </fieldset>
                </form>
            </div>

            <button class="action_buttons open_dialog btn btn-secondary"><?php echo $this->gettext('reschedule_meeting') ?></button>
            <button class="action_buttons confirm_button btn btn-secondary"><?php echo $this->gettext('confirm') ?></button>
            <button class="action_buttons tentative_button btn btn-secondary"><?php echo $this->gettext('tentative') ?></button>
            <button class="action_buttons decline_button btn btn-secondary"><?php echo $this->gettext('decline') ?></button>
        </div>
    </template>
</div>

