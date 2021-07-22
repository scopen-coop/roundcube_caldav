
<div id="conteneur">
    <div id="invitation">
        <p><?php echo $this->gettext('invitation')?></p>
    </div>
    <div id="content">
        <div class="info_ics">

            <div><?php echo $event->summary ?></div>
            <?php if (!empty($event->description)): ?>
                <p id="titre_current"> <?php echo $event->description . ' :' ?></p>
            <?php endif; ?>

            <?php if (!empty($event->location)): ?>
                <div><?php echo $event->location; ?></div>
            <?php endif; ?>

            <div><?php echo $this->pretty_date($date_start, $date_end) ?></div>

            <?php if (!empty($this->attendees)): ?>
                <div>
                    <?php echo $this->gettext("attendee"); ?>
                    <ul>
                        <?php foreach ($this->attendees as $attendee): ?>
                            <li>
                                <?php
                                $attendee["attrs"] = array(
                                    'href' => 'mailto:' . $attendee["email"],
                                    'onclick' => "return " . rcmail_output::JS_OBJECT_NAME . ".command('compose','" . rcube::JQ(format_email_recipient($attendee["email"], $attendee['name'])) . "',this)",
                                );
                                echo html::a($attendee["attrs"], $attendee['name']);
                                ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

            <?php endif; ?>
            <?php echo html::a($attrs, $this->gettext('reply_all')); ?>
        </div>
        <div class="info_caldav_server" id="info_caldav_server">
            <div class="meeting_collision">
                <?php if (!empty($display_caldav_info['collision'])): ?>
                    <p><?php echo $this->gettext('same_hour_meeting') ?></p>
                    <?php foreach ($display_caldav_info['collision'] as $calendar_name => $collision): ?>
                        <?php if (!empty($collision)): ?>
                            <b class="calendar_name"><?php echo $calendar_name ?> :</b>
                            <?php foreach ($collision as $collided_event): ?>
                                <div class="collided_event">
                                    <p><?php echo $collided_event->summary . ': ' . $this->pretty_date($collided_event->dtstart_array[1], $collided_event->dtend_array[1]) ?></p>
                                </div>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="close_meeting">
                <?php if (!empty($display_caldav_info['close_meeting']['previous'])): ?>
                    <p><?php echo $this->gettext('previous_meeting') ?></p>
                    <b class="calendar_name"><?php echo $display_caldav_info['close_meeting']['previous']['calendar'] ?>
                        :</b>
                    <p><?php echo $display_caldav_info['close_meeting']['previous']['summary'] . ': ' . $this->pretty_date($display_caldav_info['close_meeting']['previous']['date_start'], $display_caldav_info['close_meeting']['previous']['date_end']) ?></p>
                <?php endif; ?>
                <?php if (!empty($display_caldav_info['close_meeting']['next'])): ?>
                    <p><?php echo $this->gettext('next_meeting') ?></p>
                    <b class="calendar_name"><?php echo $display_caldav_info['close_meeting']['next']['calendar'] ?> :</b>
                    <p><?php echo $display_caldav_info['close_meeting']['next']['summary'] . ': ' . $this->pretty_date($display_caldav_info['close_meeting']['next']['date_start'], $display_caldav_info['close_meeting']['next']['date_end']) ?></p>
                <?php endif; ?>
            </div>

        </div>
    </div>
    <div id="action_button">
        <button id="confirm_button">confirm</button>
        <button id="tentative_button">tentative</button>
        <button id="decline_button">decline</button>
    </div>
</div>
