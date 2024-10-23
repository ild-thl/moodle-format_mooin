# MOOIN 4.1 course format
**MOOIN** stands for **Mobile Open Online Interactive eNvironment**.

It is used as Moodle course format for Massive Open Online Courses (MOOCs).
## Installation
To use the course format, at least two Moodle plugins are necessary.

We need to install the **course format**

    cd /path/to/moodle/course/format/
    
    git clone https://github.com/ild-thl/moodle-format_mooin.git mooin4

After installation, switch to the branch mooin_401 for Moodle 4.1 systems. 

    git git switch mooin_404

and the **MOOIN 4.0 Design**

    cd /path/to/moodle/theme/
    
    git clone https://github.com/ild-thl/moodle-theme_mooin.git mooin4
    
After installation, switch to the branch mooin_401 for Moodle 4.1 systems. 

    git switch mooin_404
    
For a better user experience we recommand to use **H5P** (https://moodle.org/plugins/mod_hvp). 

## Usage
First check if changing Designs in courses is enabled. Go to **Site Administration > Appearance > Theme settings** and enable **Allow course themes** (allowcoursethemes).

Then create a new course or navigate to an existing course. In the course settings go to **Course format** and choose **Mooin 4.x course format**. Then go to **Appearance > Force theme** and choose **Mooin 4.x**.

To add more chapters to the course, **turn editig on**. Move to the bottom of the course overview site and click **Add sections**. Change the name and move it to the required position. Click **Edit** at the right side of the lesson and choose **Set as chapter title**.
