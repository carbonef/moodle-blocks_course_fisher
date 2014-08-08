<?php

    defined('MOODLE_INTERNAL') || die();

    require_once($CFG->dirroot .'/course/lib.php');
    require_once($CFG->libdir .'/coursecatlib.php');

    function block_course_fisher_create_categories($categories) {
        global $DB;
        $parent = 0;

        $parentcategories = explode(',', $this->config->categories);
        foreach ($parentcategories as $categoryfieldname) {
            $newcategory = new stdClass();
            $newcategory->parent = $parent;
            if (isset($categories[$categoryfieldname])) {
                $newcategory->name = $categories[$categoryfieldname];
                if (! $oldcategory = $DB->get_record('course_categories', array('name' => $newcategory->name, 'parent' => $newcategory->parent))) {
                    $category = coursecat::create($newcategory);
                } else {
                    $category = $oldcategory->id;
                }
                $parent = $category;
            }
        }

        return $category;
    }

   /**
    * Create a course, if not exits, and assign an editing teacher
    *
    * @param string course_fullname  The course fullname
    * @param string course_shortname The course shortname
    * @param string course_id        The course code
    * @param string teacher_id       The teacher id code
    * @param array  categories       The categories from top category for this course
    *
    * @return object or null
    *
    **/
    function block_course_fisher_create_course($course_fullname, $course_shortname, $course_id, $teacher_id, $categories = array()) {
        global $DB, $CFG;

        
        $newcourse = new stdClass();

        $newcourse->id = '0';
/*
        $newcourse->MAX_FILE_SIZE = '0';
        $newcourse->format = 'topics';
        $newcourse->showgrades = '0';
        $newcourse->enablecompletion = '0';
        $newcourse->numsections = '2';
*/
        $newcourse->startdate = time();
        
        $newcourse->fullname = $course_fullname;
        $newcourse->shortname = $course_shortname;
        $newcourse->idnumber = $year.'-'.$course_id;

        $course = null;
        if (!$oldcourse = $DB->get_record('course', array('idnumber' => $newcourse->idnumber))) {
            $newcourse->category = block_course_fisher_create_categories($categories);
            if (!$course = create_course($newcourse)) {
                print_error("Error inserting a new course in the database!");
            }
        } else {
            $course = $oldcourse;
        }

        $editingteacherroleid = $DB->get_field('role', 'id', array('shortname' => 'editingteacher'));

        if ($teacheruser = $DB->get_record('user', array('id' => $teacher_id))) {
            // Set student role at course context
            $coursecontext = context_course::instance($course->id);

            $enrolled = false;
            // we use only manual enrol plugin here, if it is disabled no enrol is done
            if (enrol_is_enabled('manual')) {
                $manual = enrol_get_plugin('manual');
                if ($instances = enrol_get_instances($course->id, false)) {
                    foreach ($instances as $instance) {
                        if ($instance->enrol === 'manual') {
                            $manual->enrol_user($instance, $teacheruser->id, $editingteacherroleid, time(), 0);
                            $enrolled = true;
                            break;
                        }
                    }
                }
            }
        }

        return $course;
    }

    function block_course_fisher_backend_lang($lang, $blockstrings) {
        global $CFG;

        $backends = scandir($CFG->dirroot.'/blocks/course_fisher/backend');
        foreach ($backends as $backend) {
            if (file_exists($CFG->dirroot.'/blocks/course_fisher/backend/'.$backend.'/lang/'.$lang.'/coursefisherbackend_'.$backend.'.php')) {
                $string = array();
                include_once($CFG->dirroot.'/blocks/course_fisher/backend/'.$backend.'/lang/'.$lang.'/coursefisherbackend_'.$backend.'.php');
                foreach ($string as $name => $translation) {
error_log($name.' '.$translation);
                    $blockstrings['backend_'.$backend.':'.$name] = $translation;
                }
            }
        }
        return $blockstrings;
    }
