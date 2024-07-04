<?php

namespace format_moointopics\output\courseformat\content;


use renderable;
use core_courseformat\base as course_format;
use format_moointopics\output\courseformat\content\frontpage\header;
use format_moointopics\output\courseformat\content\frontpage\news_section;
use format_moointopics\output\courseformat\content\frontpage\courseprogress;
use format_moointopics\output\courseformat\content\frontpage\badges;
use format_moointopics\output\courseformat\content\frontpage\certificates;
use format_moointopics\output\courseformat\content\frontpage\discussions;
use format_moointopics\output\courseformat\content\frontpage\participants;
use context_course;



/**
 * Base class to render the course frontpage.
 *
 * @package   format_moointopics
 * @copyright 2023 ISy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursefrontpage implements renderable {

    /** @var course_format the course format class */
    private $format;

    /** @var header the course frontpage header class */
    private $header;

    /** @var news_section the course frontpage news section class */
    private $news_section;

    /** @var courseprogress the course frontpage progress class */
    private $courseprogress;

    private $badges;
    private $certificates;
    private $discussions;
    private $participants;


    /**
     * Constructor.
     *
     * @param course_format $format the course format
     */
    public function __construct(course_format $format) {
        $this->format = $format;
        $this->header = new header($format);
        $this->news_section = new news_section($format);
        $this->courseprogress = new courseprogress($format);
        $this->badges = new badges($format);
        $this->certificates = new certificates($format);
        $this->discussions = new discussions($format);
        $this->participants = new participants($format);
    }

    public function export_for_template(\renderer_base $output) {
        $format = $this->format;
        $header = $this->header;
        $news_section = $this->news_section;
        $courseprogress = $this->courseprogress;
        $badges = $this->badges;
        $certificates = $this->certificates;
        $discussions = $this->discussions;
        $participants = $this->participants;
        $course = $format->get_course();

        

        $data = (object)[
            'header' => $header->export_for_template($output),
            'coursename' => $course->fullname,
            'news_section' => $news_section->export_for_template($output),
            'courseprogress' => $courseprogress->export_for_template($output),
            'badges' => $badges->export_for_template($output),
            'certificates' => $certificates->export_for_template($output),
            'discussions' => $discussions->export_for_template($output),
            'participants' => $participants->export_for_template($output),
        ];

        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/course:update', $coursecontext)) {
            $data->has_capability = true;
        }

        return $data;
    }
}
