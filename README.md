# MOOIN 4.5 course format

**MOOIN** stands for **Mobile Open Online Interactive eNvironment**.

It is used as Moodle course format for Massive Open Online Courses (MOOCs).
## Installation
To use the course format, at least two Moodle plugins are necessary.

We need to install the **course format**

    cd /path/to/moodle/course/format/
    
    git clone -b mooin_405 https://github.com/ild-thl/moodle-format_mooin.git mooin4

and the **MOOIN 4.0 Design**

    cd /path/to/moodle/theme/
    
    git clone -b mooin_405 https://github.com/ild-thl/moodle-theme_mooin.git mooin4

and the **Boost Union Design**

    cd /path/to/moodle/theme/

    git clone -b MOODLE_405_STABLE https://github.com/moodle-an-hochschulen/moodle-theme_boost_union.git boost_union
    
For a better user experience we recommend to use **H5P** (https://moodle.org/plugins/mod_hvp). 

    cd /path/to/moodle/mod/

    git clone https://github.com/h5p/moodle-mod_hvp.git hvp

    cd /path/to/moodle/mod/hvp/

    git submodule update --init

## Usage
First check if changing Designs in courses is enabled. Go to **Site Administration > Appearance > Theme settings** and enable **Allow course themes** (allowcoursethemes).

Then create a new course or navigate to an existing course. In the course settings go to **Course format** and choose **Mooin 4.x course format**. Then go to **Appearance > Force theme** and choose **Mooin 4.x**.

To add more chapters to the course, **turn editig on**. Move to the bottom of the course overview site and click **Add sections**. Change the name and move it to the required position. Click **Edit** at the right side of the lesson and choose **Set as chapter title**.

## mooin_405 Features and bug fixes
Features: see https://github.com/ild-thl/moodle-format_mooin/wiki/mooin_405-Features

Bug fixes: see https://github.com/ild-thl/moodle-format_mooin/wiki/mooin_405-Bug-Fixes
