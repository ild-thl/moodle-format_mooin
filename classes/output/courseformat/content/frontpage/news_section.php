<?php

namespace format_moointopics\output\courseformat\content\frontpage;

use renderable;


/**
 * Base class to render the course news section.
 *
 * @package   format_moointopics
 * @copyright 2023 ISy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class news_section implements renderable {

    public function export_for_template(\renderer_base $output)
    {

        
        $data = (object)[
            
        ];

        return $data;
    }
}
