<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component mooin4 course format.
 *
 * @package   format_mooin4
 * @copyright 2022 ISy TH Lübeck <dev.ild@th-luebeck.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addsections'] = 'Add sections';
$string['currentsection'] = 'This section';
$string['editsection'] = 'Edit section';
$string['editsectionname'] = 'Edit section name';
$string['deletesection'] = 'Delete section';
$string['news'] = 'News';
$string['newsectionname'] = 'New name for section {$a}';
$string['sectionname'] = 'Section';
$string['pluginname'] = 'Mooin 4.0 course format';
$string['section0name'] = 'General';
$string['page-course-view-mooin4'] = 'Any course main page in Mooin 4.0 format';
$string['page-course-view-mooin4-x'] = 'Any course page in Mooin 4.0 format';
$string['hidefromothers'] = 'Hide section';
$string['showfromothers'] = 'Show section';
$string['privacy:metadata'] = 'The Mooin 4.0 format plugin does not store any personal data.';
$string['map_title'] = 'Participant map';
$string['map_descr'] = 'Please enter your location in your profile to ensure that the participant map is displayed as accurately as possible.';
$string['badges'] = 'My course badges';
$string['community'] = 'Community';
$string['forums'] = 'Discussion forums';
$string['my_forum'] = 'Forums';
$string['participants'] = 'Participants';
$string['certificates'] = 'My course certificates';
$string['badges_certificates'] = 'Badges and certificates';
$string['continue'] = 'Continue learning in lesson {$a}';
$string['continue_no_lesson'] = 'Continue learning';
$string['progress'] = 'Course progress';
$string['unenrol'] = 'Unenrol from this course';
$string['my_badges'] = 'Badges';
$string['available_badges'] = 'Available badges';
$string['awarded_badges'] = 'Recently issued badges';
$string['hide_tabs'] = 'Hide MOOC tabs';
$string['highscore'] = 'Highscore';
$string['in_course'] = 'In this course';
$string['in_mooin4'] = 'On this platform';
$string['lastday'] = 'In the last 24 hours';
$string['lastweek'] = 'In the last 7 days';
$string['discussion_link'] = 'ID of the general discussion forum';
$string['my_threads'] = 'My threads';
$string['chapter_config'] = 'Chapter';
$string['name'] = 'Name';
$string['enabled'] = 'Visible';
$string['lections'] = 'Number of lessons';
$string['configtext'] = 'Chapter options';
$string['directory_link'] = 'Directory ID for chapter images';
$string['course_badges'] = 'Course badges';
$string['overview'] = 'Badge overview';
$string['profile_badges'] = 'All badges in my profile';
$string['badge_options'] = 'Badge options';
$string['owner'] = 'Owner';
$string['no_badges_awarded'] = 'No badges have been issued in this course yet.';
$string['badge_overview_description'] = 'With course badges you can show other participants on this platform and on the web what progress you have already made. For more information, see <a href="https://en.wikipedia.org/wiki/Open_Badges" target="_blank">Wikipedia</a> and <a href="http://openbadges.org" target="_blank">OpenBadges.org</a>';
$string['all_forums'] = 'All discussion forums';
$string['course_groups'] = 'Course groups';
$string['course_groups_descr'] = 'Here you can see which groups there are in this course and decide which one you want to join:';
$string['profile_city_descr'] = 'Please enter your location in your profile to ensure that the participant map is displayed as accurately as possible.';
$string['number_of_questions'] = 'Number of capira questions';
$string['cert_descr'] = 'Congratulations! You have successfully completed at least {$a} percent of the tests included in this course. You can download your certificate of participation here.';
$string['certificate'] = 'Certificate of participation';
$string['cert_addtext'] = 'Lorem ipsum';
$string['required_questions'] = 'Required correct answers in %';
$string['only_for_trainers'] = 'Visible only for admins and trainers';
$string['my_certificate'] = 'Course certificates';
$string['certificate_overview_description'] = 'Congratulations! You have successfully completed the tests included in the course. You can download your certificate of participation here.';
$string['certificate_overview'] = 'There is no certificate for this course available yet.';

$string['start'] = 'Start';
$string['startlesson'] = 'Start in lesson 1.1';
$string['chapter'] = 'Chapter';
$string['chapter_short'] = 'Ch.';
$string['lesson'] = 'Lesson';
$string['lesson_short'] = 'Lsn.';

$string['configlabel_forcetrackforums'] = 'Force mark unread posts';
$string['configdesc_forcetrackforums'] = 'Automatically activates the global Moodle setting: <a href="{$a}/admin/search.php?query=trackreadposts">{$a}/admin/search.php?query=trackreadposts</a><br>
										  Required for users to see which forum posts have not yet been read. Unread posts can then all be marked as read with one click. This is useful if users enrol in the course at a time when a large number of posts have already been made.';
$string['update_config'] = 'Update global Moodle setting';
$string['hint_track_forums'] = 'Please update your forum preferences <a href="{$a->wwwroot}/user/forum.php?id={$a->userid}&course=1">here</a> to ensure that new posts are highlighted (Forum tracking > Yes, highlight new posts for me).';
$string['mark_all_as_read'] = 'Mark all as read';
$string['mark_all_as_read_mobile'] = 'All read';
$string['configlabel_geonamesapi_url'] = 'GeoNames API-URL';
$string['configdesc_geonamesapi_url'] = 'This service is used to display the coordinates of the participants on the participant map.';
$string['configlabel_geonamesapi_username'] = 'GeoNames user name';
$string['configdesc_geonamesapi_username'] = 'A GeoNames account is required to use this service. It can be created and activated <a href="https://www.geonames.org/">here</a>. Our Mooin 4.0 demo account should only be used for testing purposes.';
$string['configlabel_forcecompletiondefault'] = 'Do not show activity completion as default';
$string['configdesc_forcecompletiondefault'] = 'When new activities are added to the course, activity tracking is not displayed by default. Once activity completion is turned on for an activity, the progress bar for that lesson is dependent on activity completion. Otherwise, a button is displayed in the lesson footer to mark the lesson as completed.';

$string['unread_news'] = 'unread posts in the';
$string['all_news_mobile'] = 'All news';
$string['by'] = 'by ';
$string['latest_contribution_mobile'] = 'Latest post:';
$string['show_all_infos'] = 'Show details';
$string['all_news'] = 'news forum';
$string['topicoutline'] = 'Course contents';
$string['participant_map'] = 'Participant map';
$string['no_badges_image_text'] = 'No badges available';
$string['no_certificates_image_text'] = 'No certificates available';
$string['no_forums_available'] = 'No forums available';

$string['modal_course_complete'] = 'You have completed all lessons in this course!';
$string['modal_course_complete_title'] = 'Course completed';
$string['modal_chapter_complete'] = 'You have completed all lessons in this chapter!';
$string['modal_chapter_complete_title'] = 'Chapter completed';
$string['modal_last_section_of_chapter'] = 'You have reached the last lesson of this chapter!';
$string['modal_last_section_of_chapter_title'] = 'End of chapter';

// Chapter in course, abgeglichen mit Deutsch
$string['new_chapter'] = 'New chapter';
$string['new_lesson'] = 'New lesson';
$string['setchapter'] = 'Set as chapter title';
$string['unsetchapter'] = 'Set as lesson';
$string['completed'] = 'Done';
$string['headerimagedesktop'] = 'Header image desktop (800 x 280px)';
$string['headerimagemobile'] = 'Header image mobile (768 x 768px)';
$string['edit_course_header'] = 'Edit header image';
$string['cannot_remove_chapter'] = 'This chapter cannot be removed because there cannot be lessons without a parent chapter.';
$string['course_overview'] = 'Course overview';

// added by Jonas, zu Deutsch neu hinzugefügt
$string['unread_news_single'] = 'unread post in the ';
$string['unread_discussions'] = 'unread posts in the ';
$string['unread_discussions_single'] = 'unread post in the ';
$string['discussion_forum'] = 'discussion forums';
$string['next_lesson'] = 'Next lesson';
$string['previous_lesson'] = 'Previous lesson';
$string['next_chapter'] = 'Next chapter';
$string['previous_chapter'] = 'Previous chapter';
$string['discussions_tooltip'] = 'Discussion forums can be added in lessons.';
$string['certificates_tooltip'] = 'Certificates can be added in lessons.';
$string['newsforum_settings'] = 'News forum settings';
$string['course_progress_text'] = 'of the course completed';
$string['lesson_progress_text'] = 'of the lesson completed';
$string['progress_text_short'] = 'completed';
$string['mark_page_as_read'] = 'Mark page as read';
$string['page_read'] = 'Page read';


// zu Deutsch neu hinzugefügt
$string['show_all_infos'] = 'Show details';
$string['users'] = 'Users';
$string['user_card_title'] = 'Course participants';
//$string['participants'] = 'participants <span class="fw-400">in the course</span>';
$string['user'] = 'participants';
$string['in_course'] = 'in the course';
$string['new_user'] = 'Recently joined: ';
$string['no_user'] = 'No participant added';
$string['welcome'] = 'Welcome to the course';
$string['forum_unread'] = 'Unread Forums';
$string['discussions'] = 'All Discussions';

$string['discussion']= 'Latest post';
$string['all_discussions'] = 'Unread posts in forums';
$string['old_discussion'] = 'Old discussions';
$string['old_news'] = 'Old news';
$string['all_news'] = 'news forum';
$string['latest_post'] = 'Latest post';
$string['discussion_news'] = 'View post';
