<div id="conteneur" class="alert ui">
    <div id="invitation">
        <h3><?php echo $this->gettext('invitation') ?></h3>
        <h4><?php echo $event->summary ?></h4>
    </div>
    <div id="content">
        <div class="info_ics">


            <div id="date_event">

                <?php if ($same_date): ?>
                    <div class="icon_date" id="alone">
                        <div class="d"><?php echo $this->rcmail->format_date($date_start, 'd') ?></div>
                        <div class="m"><?php echo $this->rcmail->format_date($date_start, 'M') . '/' . $this->rcmail->format_date($date_start, 'Y') ?></div>
                        <div class="h"><?php echo $this->rcmail->format_date($date_start, 'G:i') . ' : ' . $this->rcmail->format_date($date_end, 'G:i') ?></div>
                    </div>
                <?php else: ?>

                    <div class="icon_date">
                        <div class="d"><?php echo $this->rcmail->format_date($date_start, 'd') ?></div>
                        <div class="m"><?php echo $this->rcmail->format_date($date_start, 'M') . '/' . $this->rcmail->format_date($date_start, 'Y') ?></div>
                        <div class="h"><?php echo $this->rcmail->format_date($date_start, 'G:i') ?></div>
                    </div>
                    <div class="arrow-right"></div>
                    <div class="icon_date">
                        <div class="d"><?php echo $this->rcmail->format_date($date_end, 'd') ?></div>
                        <div class="m"><?php echo $this->rcmail->format_date($date_end, 'M') . '/' . $this->rcmail->format_date($date_end, 'Y') ?></div>
                        <div class="h"><?php echo $this->rcmail->format_date($date_end, 'G:i') ?></div>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <?php if (!empty($event->location)): ?>
                    <p><?php echo '<b>' . $this->gettext("location") . '</b>' . $event->location; ?></p>
                <?php endif; ?>

                <?php if (!empty($event->description)): ?>
                    <p> <?php echo '<b>' . $this->gettext("description") . '</b>' . $event->description . ' :' ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($this->attendees)): ?>
                <div>
                    <p><b><?php echo $this->gettext("attendee"); ?></b></p>
                    <div class='attendee_link'>
                        <?php foreach ($this->attendees as $attendee): ?>
                            <a href='mailto:<?php echo $attendee["email"] ?>' aria-haspopup="false"
                               onclick="<?php echo $attendee["onclick"] ?>">
                                <?php echo $attendee['name'] ?>
                            </a>
                            <br/>
                        <?php endforeach; ?>
                    </div>

                </div>
                <div id="reply_all"><?php echo html::a($attrs, $this->gettext('reply_all')); ?></div>
            <?php endif; ?>


        </div>
        <div id="info_caldav_server">
            <div class="close_meeting">
                <?php if (!empty($display_caldav_info['close_meeting']['previous'])): ?>
                    <p><?php echo $this->gettext('previous_meeting') ?></p>

                    <p class="meeting_display"><?php echo $display_caldav_info['close_meeting']['previous']['summary']
                            . ': ' . '<i>' . '(' . $display_caldav_info['close_meeting']['previous']['calendar'] . ')' . '</i><br>'
                            . $this->pretty_date($display_caldav_info['close_meeting']['previous']['date_start'], $display_caldav_info['close_meeting']['previous']['date_end']) ?>
                    </p>
                <?php endif; ?>
            </div>
            <br/>
            <?php if (!empty($display_caldav_info['collision'])): ?>
                <div class="meeting_collision">

                    <p><?php echo $this->gettext('same_hour_meeting') ?></p>
                    <?php foreach ($display_caldav_info['collision'] as $calendar_name => $collision): ?>
                        <?php if (!empty($collision)): ?>
                            <?php foreach ($collision as $collided_event): ?>
                                <div class="collided_event">
                                    <p class="meeting_display"><?php echo $collided_event->summary . ': ' . '<i>' . '('
                                            . $calendar_name . ')' . '</i><br/>' . $this->pretty_date($collided_event->dtstart_array[1], $collided_event->dtend_array[1]) ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <br/>
            <div class="close_meeting">
                <?php if (!empty($display_caldav_info['close_meeting']['next'])): ?>
                    <p><?php echo $this->gettext('next_meeting') ?></p>
                    <p class="meeting_display"><?php echo $display_caldav_info['close_meeting']['next']['summary']
                            . ': ' . '<i>' . '(' . $display_caldav_info['close_meeting']['next']['calendar'] . ')' . '</i><br>'
                            . $this->pretty_date($display_caldav_info['close_meeting']['next']['date_start'], $display_caldav_info['close_meeting']['next']['date_end']) ?>
                    </p>
                <?php endif; ?>
            </div>

        </div>
    </div>
    <div id="action_button">
        <form method="post" name="chosen_cal">
            <label>
                Choose Calendar
                <select id="choose_calendar_to_add_event">
                    <?php foreach ($_used_calendars as $calendar): ?>
                        <option value="<?php echo $calendar ?>" <?php echo ($calendar == $main_calendar) ? 'selected' : '' ?>><?php echo $calendar ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <input type="submit" value="Submit the form"/>
        </form>

        <button id="confirm_button"><?php echo $this->gettext('confirm') ?></button>
        <button id="tentative_button"><?php echo $this->gettext('tentative') ?></button>
        <button id="decline_button"><?php echo $this->gettext('decline') ?></button>
    </div>
</div>

