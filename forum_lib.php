<?php

/**
 * Prints the discussion view screen for a forum.
 *
 * @global object
 * @global object
 * @param object $course The current course object.
 * @param object $forum Forum to be printed.
 * @param int $maxdiscussions .
 * @param string $displayformat The display format to use (optional).
 * @param string $sort Sort arguments for database query (optional).
 * @param int $groupmode Group mode of the forum (optional).
 * @param void $unused (originally current group)
 * @param int $page Page mode, page to display (optional).
 * @param int $perpage The maximum number of discussions per page(optional)
 * @param boolean $subscriptionstatus Whether the user is currently subscribed to the discussion in some fashion.
 *
 */
function oc_forum_print_latest_discussions($course, $forum, $maxdiscussions = -1, $displayformat = 'plain', $sort = '',
                                        $currentgroup = -1, $groupmode = -1, $page = -1, $perpage = 100, $cm = null) {
    global $CFG, $USER, $OUTPUT;

    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('forum', $forum->id, $forum->course)) {
            print_error('invalidcoursemodule');
        }
    }
    $context = context_module::instance($cm->id);

    if (empty($sort)) {
        $sort = "d.timemodified DESC";
    }

    $olddiscussionlink = false;

 // Sort out some defaults
    if ($perpage <= 0) {
        $perpage = 0;
        $page    = -1;
    }

    if ($maxdiscussions == 0) {
        // all discussions - backwards compatibility
        $page    = -1;
        $perpage = 0;
        if ($displayformat == 'plain') {
            $displayformat = 'header';  // Abbreviate display by default
        }

    } else if ($maxdiscussions > 0) {
        $page    = -1;
        $perpage = $maxdiscussions;
    }

    $fullpost = false;
    if ($displayformat == 'plain') {
        $fullpost = true;
    }


// Decide if current user is allowed to see ALL the current discussions or not

// First check the group stuff
    if ($currentgroup == -1 or $groupmode == -1) {
        $groupmode    = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm);
    }

    $groups = array(); //cache

// If the user can post discussions, then this is a good place to put the
// button for it. We do not show the button if we are showing site news
// and the current user is a guest.

    $canstart = forum_user_can_post_discussion($forum, $currentgroup, $groupmode, $cm, $context);
    if (!$canstart and $forum->type !== 'news') {
        if (isguestuser() or !isloggedin()) {
            $canstart = true;
        }
        if (!is_enrolled($context) and !is_viewing($context)) {
            // allow guests and not-logged-in to see the button - they are prompted to log in after clicking the link
            // normal users with temporary guest access see this button too, they are asked to enrol instead
            // do not show the button to users with suspended enrolments here
            $canstart = enrol_selfenrol_available($course->id);
        }
    }

    if ($canstart) {
        echo '<div class="py-3">'; // singlebutton forumaddnew
        echo "<form  id=\"newdiscussionform\" method=\"get\" action=\"$CFG->wwwroot/course/format/mooin4/forum_post.php\">";
        echo '<div>';
        echo "<input type=\"hidden\" name=\"forum\" value=\"$forum->id\" />";
        switch ($forum->type) {
            case 'news':
            case 'blog':
                $buttonadd = get_string('addanewtopic', 'forum');
                break;
            case 'qanda':
                $buttonadd = get_string('addanewquestion', 'forum');
                break;
            default:
                $buttonadd = get_string('addanewdiscussion', 'forum');
                break;
        }
        echo '<input class="btn btn-primary mb-2" type="submit" value="'.$buttonadd.'" />';
        echo '</div>';
        echo '</form>';
        echo "</div>\n";

    } else if (isguestuser() or !isloggedin() or $forum->type == 'news' or
        $forum->type == 'qanda' and !has_capability('mod/forum:addquestion', $context) or
        $forum->type != 'qanda' and !has_capability('mod/forum:startdiscussion', $context)) {
        // no button and no info

    } else if ($groupmode and !has_capability('moodle/site:accessallgroups', $context)) {
        // inform users why they can not post new discussion
        if (!$currentgroup) {
            echo $OUTPUT->notification(get_string('cannotadddiscussionall', 'forum'));
        } else if (!groups_is_member($currentgroup)) {
            echo $OUTPUT->notification(get_string('cannotadddiscussion', 'forum'));
        }
    }

// Get all the recent discussions we're allowed to see

    $getuserlastmodified = ($displayformat == 'header');

    if (! $discussions = forum_get_discussions($cm, $sort, $fullpost, null, $maxdiscussions, $getuserlastmodified, $page, $perpage) ) {
        echo '<div class="forumnodiscuss">';
        if ($forum->type == 'news') {
            echo '('.get_string('nonews', 'forum').')';
        } else if ($forum->type == 'qanda') {
            echo '('.get_string('noquestions','forum').')';
        } else {
            echo '('.get_string('nodiscussions', 'forum').')';
        }
        echo "</div>\n";
        return;
    }

// If we want paging
    if ($page != -1) {
        ///Get the number of discussions found
        $numdiscussions = forum_get_discussions_count($cm);

        ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$forum->id");
        if ($numdiscussions > 1000) {
            // saves some memory on sites with very large forums
            $replies = forum_count_discussion_replies($forum->id, $sort, $maxdiscussions, $page, $perpage);
        } else {
            $replies = forum_count_discussion_replies($forum->id);
        }

    } else {
        $replies = forum_count_discussion_replies($forum->id);

        if ($maxdiscussions > 0 and $maxdiscussions <= count($discussions)) {
            $olddiscussionlink = true;
        }
    }

    $canviewparticipants = has_capability('moodle/course:viewparticipants',$context);

    $strdatestring = get_string('strftimedatefullshort'); // strftimerecentfull

    // Check if the forum is tracked.
    if ($cantrack = forum_tp_can_track_forums($forum)) {
        $forumtracked = forum_tp_is_tracked($forum);
    } else {
        $forumtracked = false;
    }

    if ($forumtracked) {
        $unreads = forum_get_discussions_unread($cm);
    } else {
        $unreads = array();
    }

    if ($displayformat == 'header') {
        echo '<table cellspacing="0" class="table discussion-list">'; // forumheaderlist
        echo '<thead>';
        echo '<tr>';
        echo '<th class="header topic" scope="col">'.get_string('discussion', 'forum').'</th>';
        echo '<th class="header author" scope="col">'.get_string('startedby', 'forum').'</th>';
        if ($groupmode > 0) {
            echo '<th class="header group" scope="col">'.get_string('group').'</th>';
        }
        
        if (has_capability('mod/forum:viewdiscussion', $context)) {
            echo '<th class="header replies" scope="col">'.get_string('replies', 'forum').'</th>';
            // If the forum can be tracked, display the unread column.
            if ($cantrack) {
                echo '<th class="header replies" scope="col">'.get_string('unread', 'forum');
                if ($forumtracked) {
                    echo '<a title="'.get_string('markallread', 'forum').
                         '" href="'.$CFG->wwwroot.'/mod/forum/markposts.php?f='.
                         $forum->id.'&amp;mark=read&amp;returnpage=view.php&sesskey='.sesskey().'">'.
                         '<img src="'.$OUTPUT->image_url('t/markasread') . '" class="iconsmall" alt="'.get_string('markallread', 'forum').'" /></a>'; // $OUTPUT->pix_url('t/markasread')
                }
                echo '</th>';
            }
        }
        echo '<th class="header lastpost" scope="col">'.get_string('lastpost', 'forum').'</th>';
        if ((!is_guest($context, $USER) && isloggedin()) && has_capability('mod/forum:viewdiscussion', $context)) {
            if (\mod_forum\subscriptions::is_subscribable($forum)) {
                echo '<th class="header discussionsubscription" scope="col">';
                echo forum_get_discussion_subscription_icon_preloaders();
                echo '</th>';
            }
        }
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
    }

    foreach ($discussions as $discussion) {
        if ($forum->type == 'qanda' && !has_capability('mod/forum:viewqandawithoutposting', $context) &&
            !forum_user_has_posted($forum->id, $discussion->discussion, $USER->id)) {
            $canviewparticipants = false;
        }

        if (!empty($replies[$discussion->discussion])) {
            $discussion->lastpostid = $replies[$discussion->discussion]->lastpostid;
            $discussion->replies = $replies[$discussion->discussion]->replies;
            
        } else {
            $discussion->replies = 0;
        }

        // SPECIAL CASE: The front page can display a news item post to non-logged in users.
        // All posts are read in this case.
        if (!$forumtracked) {
            $discussion->unread = '-';
        } else if (empty($USER)) {
            $discussion->unread = 0;
        } else {
            if (empty($unreads[$discussion->discussion])) {
                $discussion->unread = 0;
            } else {
                $discussion->unread = $unreads[$discussion->discussion];
            }
        }

        if (isloggedin()) {
            $ownpost = ($discussion->userid == $USER->id);
        } else {
            $ownpost=false;
        }
        // Use discussion name instead of subject of first post
        $discussion->subject = $discussion->name;

        switch ($displayformat) {
            case 'header':
                if ($groupmode > 0) {
                    if (isset($groups[$discussion->groupid])) {
                        $group = $groups[$discussion->groupid];
                    } else {
                        $group = $groups[$discussion->groupid] = groups_get_group($discussion->groupid);
                    }
                } else {
                    $group = -1;
                }
                forum_print_discussion_header($discussion, $forum, $group, $strdatestring, $cantrack, $forumtracked,
                    $canviewparticipants, $context);
            break;
            default:
                $link = false;

                if ($discussion->replies) {
                    $link = true;
                } else {
                    $modcontext = context_module::instance($cm->id);
                    $link = forum_user_can_see_discussion($forum, $discussion, $modcontext, $USER);
                }

                $discussion->forum = $forum->id;

                forum_print_post($discussion, $discussion, $forum, $cm, $course, $ownpost, 0, $link, false,
                        '', null, true, $forumtracked);
            break;
        }
    }

    if ($displayformat == "header") {
        echo '</tbody>';
        echo '</table>';
    }

    if ($olddiscussionlink) {
        if ($forum->type == 'news') {
            $strolder = get_string('oldertopics', 'forum');
        } else {
            $strolder = get_string('olderdiscussions', 'forum');
        }
        echo '<div class="forumolddiscuss">';
        echo '<a href="'.$CFG->wwwroot.'/mod/forum/view.php?f='.$forum->id.'&amp;showall=1">';
        echo $strolder.'</a> ...</div>';
    }

    if ($page != -1) { ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$forum->id");
    }
}

?>