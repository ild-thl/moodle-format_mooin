<?php

namespace format_mooin\task;

/**
 * Scheduled task for updating trackreadposts in global config,
 * if checked in plugin settings.
 */
class update_config extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('update_config', 'format_mooin');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        if (get_config('format_mooin', 'forcetrackforums')) {
            if (!get_config('moodle', 'forum_trackreadposts')) {
                mtrace('Set value of setting forum_trackreadposts to 1');
                set_config('forum_trackreadposts', 1);
                if (get_config('moodle', 'forum_trackreadposts')) {
                    mtrace('Success!');
                }
                else {
                    mtrace('Error!');
                }
            }
        }
        if (get_config('format_mooin', 'forcecompletiondefault')) {
            if (get_config('moodle', 'completiondefault') == 1) {
                mtrace('Set value of setting completiondefault to 0');
                set_config('completiondefault', 0);
                if (get_config('moodle', 'completiondefault') == 0) {
                    mtrace('Success!');
                }
                else {
                    mtrace('Error!');
                }
            }
        }
    }
}