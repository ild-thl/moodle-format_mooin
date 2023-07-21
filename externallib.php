<?php

require_once($CFG->libdir . "/externallib.php");
require_once('locallib.php');
require_once($CFG->dirroot . '/course/lib.php');



class format_mooin4_external extends external_api
{

  // public static function execute_parameters()
  // {
  //   return new external_function_parameters([
  //     'section' => new external_value(PARAM_INT, 'Current Section ID'),
  //   ]);
  // }

  /**
   * Returns description of method parameters
   * @return external_function_parameters
   */
  public static function check_completion_status_parameters()
  {
    return new external_function_parameters(array(
      //'course_id' => new external_value(PARAM_INT, 'Course ID'),
      'section_id' => new external_value(PARAM_INT, 'Current Section ID'),
      'isActivity' => new external_value(PARAM_BOOL, 'is it hvp?'),
      'course_already_completed' => new external_value(PARAM_BOOL, 'Check if the Course is completetd already'),
      'chapter_already_completed' => new external_value(PARAM_BOOL, 'Check if the current Chapter is completetd already'),


      // 'score' => new external_value(PARAM_FLOAT, 'H5P score'),
      // 'maxscore' => new external_value(PARAM_FLOAT, 'H5P max score')
    ));
  }



  /**
   * Returns status
   * @return array user data
   */
  public static function check_completion_status($section_id, $isActivity, $course_already_completed, $chapter_already_completed)
  {
    global $DB, $SESSION, $PAGE, $USER;



    $params = self::validate_parameters(
      self::check_completion_status_parameters(),
      array(
        //'course_id' => $course_id,
        'section_id' => $section_id,
        'isActivity' => $isActivity,
        'course_already_completed' => $course_already_completed,
        'chapter_already_completed' => $chapter_already_completed
      )
    );
    //$course = $DB->get_record('course', array('id' => $params['course_id']), '*', MUST_EXIST);
    //$context = context_course::instance($course->id, MUST_EXIST);
    $context = context_system::instance();
    self::validate_context($context);

    if (!$isActivity) {
      complete_section($section_id, $USER->id);
    }

    $section = $DB->get_record('course_sections', array('id' => $params['section_id']));
    $course_id = $section->course;
    $parent_chapter = get_parent_chapter($section);
    $info = get_chapter_info($parent_chapter);

    $is_course_completed = is_course_completed($course_id);

    // $is_course_completed = false;
    // if ($course_chapters = $DB->get_records('format_mooin4_chapter', array('courseid' => $course_id))) {
    //   $is_course_completed = true;
    //   foreach ($course_chapters as $chapter) {
    //     $chapter_info = get_chapter_info($chapter);
    //     if ($chapter_info['completed'] == false) {
    //       $is_course_completed = false;
    //       break;
    //     }
    //   }
    // }

    // Get the Section for the next Chapter
    $chapter_forward = null;
    if ($next_chapter = $DB->get_record('format_mooin4_chapter', array('courseid' => $course_id, 'chapter' => ($parent_chapter->chapter) + 1))) {
      $next_chapter_section = $DB->get_record('course_sections', array('id' => $next_chapter->sectionid));
      $chapter_forward = $next_chapter_section->section + 1;
      //$chapter_forward = $chapter_forward +1;
    } else {
      $chapter_forward = -1;
    }
    $show_chapter_modal = false;
    if (!$chapter_already_completed) {
      if ($info['completed']) {
        $show_chapter_modal = true;
      }
    }

    $show_course_modal = false;

    if (!$course_already_completed) {
      if ($is_course_completed == true) {
          $show_chapter_modal = false;
          $show_course_modal = true;
        }
    }

    // if ($is_course_completed == true) {
    //   $show_chapter_modal = false;
    //   $show_course_modal = true;
    // }



    // if ($info['completed']==true) {
    //   return array('chapter_completed' => true);
    //   //echo json_encode(array('completed' => true));
    // } else {
    //   return array('chapter_completed' => false);
    //   //echo json_encode(array('completed' => false));
    // }
    return array(
      'show_chapter_modal' => $show_chapter_modal,
      'show_course_modal' => $show_course_modal,
      'chapter_id' => $parent_chapter->sectionid,
      'course_id' => $course_id,
      'next_chapter' => $chapter_forward
    );
  }

  /**
   * Returns description of method result value
   * @return external_single_structure
   */
  public static function check_completion_status_returns()
  {
    return new external_single_structure(array(
      'show_chapter_modal' => new external_value(PARAM_BOOL, 'if chapter is completed with section completion'),
      'show_course_modal' => new external_value(PARAM_BOOL, 'if course is completed'),
      'chapter_id' => new external_value(PARAM_INT, 'id of the sections chapter'),
      'course_id' => new external_value(PARAM_INT, 'Course id'),
      'next_chapter' =>  new external_value(PARAM_INT, 'Next Chapter'),
    ));
  }



  /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function setgrade_parameters() {
      return new external_function_parameters(array(
          'contentid' => new external_value(PARAM_INT, 'H5P content id'),
          'score' => new external_value(PARAM_FLOAT, 'H5P score'),
          'maxscore' => new external_value(PARAM_FLOAT, 'H5P max score')
      ));
  }

  /**
   * Returns status
   * @return array user data
   */
  public static function setgrade($contentid, $score, $maxscore) {
      global $SESSION, $DB, $CFG;
      require_once($CFG->dirroot . '/mod/hvp/lib.php');
      $cm = get_coursemodule_from_instance('hvp', $contentid);


      //Parameter validation
      //REQUIRED
      $params = self::validate_parameters(self::setgrade_parameters(),
          array(
              'contentid' => $contentid,
              'score' => $score,
              'maxscore' => $maxscore
          ));

      //Context validation
      //OPTIONAL but in most web service it should present
      $context = \context_system::instance();
      self::validate_context($context);

      $courseid = $cm->course;
      $course_already_completed = is_course_completed($courseid);

      $section_id = $cm->section;
      $section = $DB->get_record('course_sections', array('id' => $section_id));
      $parent_chapter = get_parent_chapter($section);
      $info = get_chapter_info($parent_chapter);
      $chapter_already_completed = false;
      if ($info['completed']) {
        $chapter_already_completed = true;

      }

      // if ($info['completed']) {
      //   $chapter_already_completed = true;
      // }

      $progress = setgrade($contentid, $score, $maxscore);
      //$section = $DB->get_record('course_sections', array('id' => $progress['sectionid']));
      //$courseid = $section->course;
      //$course_already_completed = is_course_completed($courseid);


      return array(
          'sectionid' => $progress['sectionid'],
          'percentage' => $progress['percentage'],
          'course_already_completed' => $course_already_completed,
          'chapter_already_completed' => $chapter_already_completed,
          'courseid' => $courseid,
          'sectionid' => $section_id,
      );
  }

  /**
   * Returns description of method result value
   * @return external_single_structure
   */
  public static function setgrade_returns() {
      return new \external_single_structure(array(
          'sectionid' => new external_value(PARAM_INT, 'Section ID'),
          'percentage' => new external_value(PARAM_FLOAT, 'Percentage of section progress'),
          'course_already_completed' => new external_value(PARAM_BOOL, 'Check if the Course is completetd already'),
          'chapter_already_completed' => new external_value(PARAM_BOOL, 'Check if the current Chapter is completetd already'),
          'courseid' => new external_value(PARAM_INT, 'Course ID'),
          'sectionid' => new external_value(PARAM_INT, 'Section ID'),
      ), 'Section progress');
  }
}
