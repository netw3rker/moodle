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
 * External cohort API
 *
 * @package    core_cohort
 * @category   external
 * @copyright  MediaTouch 2000 srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/cohort/externallib.php');

class core_cohort_externallib_testcase extends externallib_advanced_testcase {

    /**
     * Test create_cohorts
     */
    public function test_create_cohorts() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        $contextid = context_system::instance()->id;
        $category = $this->getDataGenerator()->create_category();

        $cohort1 = array(
            'categorytype' => array('type' => 'id', 'value' => $category->id),
            'name' => 'cohort test 1',
            'idnumber' => 'cohorttest1',
            'description' => 'This is a description for cohorttest1'
            );

        $cohort2 = array(
            'categorytype' => array('type' => 'system', 'value' => ''),
            'name' => 'cohort test 2',
            'idnumber' => 'cohorttest2',
            'description' => 'This is a description for cohorttest2',
            'visible' => 0
            );

        $cohort3 = array(
            'categorytype' => array('type' => 'id', 'value' => $category->id),
            'name' => 'cohort test 3',
            'idnumber' => 'cohorttest3',
            'description' => 'This is a description for cohorttest3'
            );
        $roleid = $this->assignUserCapability('moodle/cohort:manage', $contextid);

        // Call the external function.
        $this->setCurrentTimeStart();
        $createdcohorts = core_cohort_external::create_cohorts(array($cohort1, $cohort2));

        // Check we retrieve the good total number of created cohorts + no error on capability.
        $this->assertEquals(2, count($createdcohorts));

        foreach ($createdcohorts as $createdcohort) {
            $dbcohort = $DB->get_record('cohort', array('id' => $createdcohort['id']));
            if ($createdcohort['idnumber'] == $cohort1['idnumber']) {
                $conid = $DB->get_field('context', 'id', array('instanceid' => $cohort1['categorytype']['value'],
                        'contextlevel' => CONTEXT_COURSECAT));
                $this->assertEquals($dbcohort->contextid, $conid);
                $this->assertEquals($dbcohort->name, $cohort1['name']);
                $this->assertEquals($dbcohort->description, $cohort1['description']);
                $this->assertEquals($dbcohort->visible, 1); // Field was not specified, ensure it is visible by default.
            } else if ($createdcohort['idnumber'] == $cohort2['idnumber']) {
                $this->assertEquals($dbcohort->contextid, context_system::instance()->id);
                $this->assertEquals($dbcohort->name, $cohort2['name']);
                $this->assertEquals($dbcohort->description, $cohort2['description']);
                $this->assertEquals($dbcohort->visible, $cohort2['visible']);
            } else {
                $this->fail('Unrecognised cohort found');
            }
            $this->assertTimeCurrent($dbcohort->timecreated);
            $this->assertTimeCurrent($dbcohort->timemodified);
        }

        // Call without required capability.
        $this->unassignUserCapability('moodle/cohort:manage', $contextid, $roleid);
        $this->setExpectedException('required_capability_exception');
        $createdcohorts = core_cohort_external::create_cohorts(array($cohort3));
    }

    /**
     * Test delete_cohorts
     */
    public function test_delete_cohorts() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        $cohort1 = self::getDataGenerator()->create_cohort();
        $cohort2 = self::getDataGenerator()->create_cohort();
        $cohort3 = self::getDataGenerator()->create_cohort(array('idnumber' => 'externalcohort1'));
        $cohort4 = self::getDataGenerator()->create_cohort(array('idnumber' => 'externalcohort2'));
        // Check the cohorts were correctly created.
        $this->assertEquals(4, $DB->count_records_select('cohort', ' (id in (:cohortid1, :cohortid2,  :cohortid3,  :cohortid4))',
                array(
                    'cohortid1' => $cohort1->id,
                    'cohortid2' => $cohort2->id,
                    'cohortid3' => $cohort3->id,
                    'cohortid4' => $cohort4->id
                )));

        $contextid = $cohort1->contextid;
        $roleid = $this->assignUserCapability('moodle/cohort:manage', $contextid);

        // Call the external function and delete by ID.
        core_cohort_external::delete_cohorts(array($cohort1->id, $cohort2->id));

        // Check we retrieve no cohorts + no error on capability.
        $this->assertEquals(0, $DB->count_records_select('cohort', ' (id = :cohortid1 OR id = :cohortid2)',
                array('cohortid1' => $cohort1->id, 'cohortid2' => $cohort2->id)));

        core_cohort_external::delete_cohorts(array($cohort3->idnumber, $cohort4->idnumber), 'idnumber');

        // Check we retrieve no cohorts + no error on capability.
        $this->assertEquals(0, $DB->count_records_select('cohort', ' (id = :cohortid3 OR id = :cohortid4)',
          array('cohortid3' => $cohort3->id, 'cohortid4' => $cohort4->id)));

        // Call without required capability.
        $cohort1 = self::getDataGenerator()->create_cohort();
        $cohort2 = self::getDataGenerator()->create_cohort();
        $this->unassignUserCapability('moodle/cohort:manage', $contextid, $roleid);
        $this->setExpectedException('required_capability_exception');
        core_cohort_external::delete_cohorts(array($cohort1->id, $cohort2->id));
    }

    /**
     * Test get_cohorts
     */
    public function test_get_cohorts() {
        global $USER, $CFG;

        $this->resetAfterTest(true);

        $cohort1 = array(
            'contextid' => 1,
            'name' => 'cohortnametest1',
            'idnumber' => 'idnumbertest1',
            'description' => 'This is a description for cohort 1'
            );
        $cohort1 = self::getDataGenerator()->create_cohort($cohort1);
        $cohort2 = self::getDataGenerator()->create_cohort();

        $context = context_system::instance();
        $roleid = $this->assignUserCapability('moodle/cohort:view', $context->id);

        // Call the external function to get cohorts by id.
        $returnedcohorts = core_cohort_external::get_cohorts(array(
            $cohort1->id, $cohort2->id));

        // Check we retrieve the good total number of enrolled cohorts + no error on capability.
        $this->assertEquals(2, count($returnedcohorts));

        foreach ($returnedcohorts as $enrolledcohort) {
            if ($enrolledcohort['idnumber'] == $cohort1->idnumber) {
                $this->assertEquals($cohort1->name, $enrolledcohort['name']);
                $this->assertEquals($cohort1->description, $enrolledcohort['description']);
                $this->assertEquals($cohort1->visible, $enrolledcohort['visible']);
            }
        }

        // Call the external function to get cohorts by idnumber.
        $returnedcohorts = core_cohort_external::get_cohorts(array(
          $cohort1->idnumber, $cohort2->idnumber), 'idnumber');

        // Check we retrieve the good total number of enrolled cohorts + no error on capability.
        $this->assertEquals(2, count($returnedcohorts));

        foreach ($returnedcohorts as $enrolledcohort) {
            if ($enrolledcohort['idnumber'] == $cohort1->idnumber) {
                $this->assertEquals($cohort1->name, $enrolledcohort['name']);
                $this->assertEquals($cohort1->description, $enrolledcohort['description']);
                $this->assertEquals($cohort1->visible, $enrolledcohort['visible']);
            }
        }

        // Check that a user with cohort:manage can see the cohort.
        $this->unassignUserCapability('moodle/cohort:view', $context->id, $roleid);
        $roleid = $this->assignUserCapability('moodle/cohort:manage', $context->id, $roleid);
        // Call the external function.
        $returnedcohorts = core_cohort_external::get_cohorts(array(
            $cohort1->id, $cohort2->id));

        // Check we retrieve the good total number of enrolled cohorts + no error on capability.
        $this->assertEquals(2, count($returnedcohorts));
    }

    /**
     * Test update_cohorts
     */
    public function test_update_cohorts() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        $cohort1 = self::getDataGenerator()->create_cohort(array('visible' => 0));
        // This 2nd cohort is generated with an idnumber for testing.
        $cohort2 = self::getDataGenerator()->create_cohort(array('visible' => 0, 'idnumber' => 'idnumbertest2'));

        $cohort1 = array(
            'id' => $cohort1->id,
            'categorytype' => array('type' => 'id', 'value' => '1'),
            'name' => 'cohortnametest1',
            'idnumber' => 'idnumbertest1',
            'description' => 'This is a description for cohort 1'
            );
        $cohort2 = array(
          'categorytype' => array('type' => 'id', 'value' => '1'),
          'name' => 'cohortnametest2',
          'idnumber' => $cohort2->idnumber,
          'description' => 'This is a description for cohort 2'
        );

        $context = context_system::instance();
        $roleid = $this->assignUserCapability('moodle/cohort:manage', $context->id);

        // Call the external function to update by id field.
        core_cohort_external::update_cohorts(array($cohort1));

        $dbcohort = $DB->get_record('cohort', array('id' => $cohort1['id']));
        $contextid = $DB->get_field('context', 'id', array('instanceid' => $cohort1['categorytype']['value'],
        'contextlevel' => CONTEXT_COURSECAT));
        $this->assertEquals($dbcohort->contextid, $contextid);
        $this->assertEquals($dbcohort->name, $cohort1['name']);
        $this->assertEquals($dbcohort->idnumber, $cohort1['idnumber']);
        $this->assertEquals($dbcohort->description, $cohort1['description']);
        $this->assertEquals($dbcohort->visible, 0);

        // Test cohorts updating by idnumber field.
        core_cohort_external::update_cohorts(array($cohort2), 'idnumber');

        $dbcohort = $DB->get_record('cohort', array('idnumber' => $cohort2['idnumber']));
        $contextid = $DB->get_field('context', 'id', array('instanceid' => $cohort2['categorytype']['value'],
          'contextlevel' => CONTEXT_COURSECAT));
        $this->assertEquals($dbcohort->contextid, $contextid);
        $this->assertEquals($dbcohort->name, $cohort2['name']);
        $this->assertEquals($dbcohort->idnumber, $cohort2['idnumber']);
        $this->assertEquals($dbcohort->description, $cohort2['description']);
        $this->assertEquals($dbcohort->visible, 0);

        // Since field 'visible' was added in 2.8, make sure that update works correctly with and without this parameter.
        core_cohort_external::update_cohorts(array($cohort1 + array('visible' => 1)));
        $dbcohort = $DB->get_record('cohort', array('id' => $cohort1['id']));
        $this->assertEquals(1, $dbcohort->visible);
        core_cohort_external::update_cohorts(array($cohort1));
        $dbcohort = $DB->get_record('cohort', array('id' => $cohort1['id']));
        $this->assertEquals(1, $dbcohort->visible);

        // Call without required capability.
        $this->unassignUserCapability('moodle/cohort:manage', $context->id, $roleid);
        $this->setExpectedException('required_capability_exception');
        core_cohort_external::update_cohorts(array($cohort1));
    }

    /**
     * Verify handling of 'id' param.
     */
    public function test_update_cohorts_invalid_id_param() {
        $this->resetAfterTest(true);
        $cohort = self::getDataGenerator()->create_cohort();

        $cohort1 = array(
            'id' => 'THIS IS NOT AN ID',
            'name' => 'Changed cohort name',
            'categorytype' => array('type' => 'id', 'value' => '1'),
            'idnumber' => $cohort->idnumber,
        );

        try {
            core_cohort_external::update_cohorts(array($cohort1));
            $this->fail('Expecting invalid_parameter_exception exception, none occured');
        } catch (invalid_parameter_exception $e1) {
            $this->assertContains('Invalid external api parameter: the value is "THIS IS NOT AN ID"', $e1->debuginfo);
        }

        $cohort1['id'] = 9.999; // Also not a valid id of a cohort.
        try {
            core_cohort_external::update_cohorts(array($cohort1));
            $this->fail('Expecting invalid_parameter_exception exception, none occured');
        } catch (invalid_parameter_exception $e2) {
            $this->assertContains('Invalid external api parameter: the value is "9.999"', $e2->debuginfo);
        }
    }

    /**
     * Test update_cohorts without permission on the dest category.
     */
    public function test_update_cohorts_missing_dest() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        $category1 = self::getDataGenerator()->create_category(array(
            'name' => 'Test category 1'
        ));
        $category2 = self::getDataGenerator()->create_category(array(
            'name' => 'Test category 2'
        ));
        $context1 = context_coursecat::instance($category1->id);
        $context2 = context_coursecat::instance($category2->id);

        $cohort = array(
            'contextid' => $context1->id,
            'name' => 'cohortnametest1',
            'idnumber' => 'idnumbertest1',
            'description' => 'This is a description for cohort 1'
            );
        $cohort1 = self::getDataGenerator()->create_cohort($cohort);

        $roleid = $this->assignUserCapability('moodle/cohort:manage', $context1->id);

        $cohortupdate = array(
            'id' => $cohort1->id,
            'categorytype' => array('type' => 'id', 'value' => $category2->id),
            'name' => 'cohort update',
            'idnumber' => 'idnumber update',
            'description' => 'This is a description update'
            );

        // Call the external function.
        // Should fail because we don't have permission on the dest category
        $this->setExpectedException('required_capability_exception');
        core_cohort_external::update_cohorts(array($cohortupdate));
    }

    /**
     * Test update_cohorts without permission on the src category.
     */
    public function test_update_cohorts_missing_src() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        $category1 = self::getDataGenerator()->create_category(array(
            'name' => 'Test category 1'
        ));
        $category2 = self::getDataGenerator()->create_category(array(
            'name' => 'Test category 2'
        ));
        $context1 = context_coursecat::instance($category1->id);
        $context2 = context_coursecat::instance($category2->id);

        $cohort = array(
            'contextid' => $context1->id,
            'name' => 'cohortnametest1',
            'idnumber' => 'idnumbertest1',
            'description' => 'This is a description for cohort 1'
            );
        $cohort1 = self::getDataGenerator()->create_cohort($cohort);

        $roleid = $this->assignUserCapability('moodle/cohort:manage', $context2->id);

        $cohortupdate = array(
            'id' => $cohort1->id,
            'categorytype' => array('type' => 'id', 'value' => $category2->id),
            'name' => 'cohort update',
            'idnumber' => 'idnumber update',
            'description' => 'This is a description update'
            );

        // Call the external function.
        // Should fail because we don't have permission on the src category
        $this->setExpectedException('required_capability_exception');
        core_cohort_external::update_cohorts(array($cohortupdate));
    }

    /**
     * Test add_cohort_members
     */
    public function test_add_cohort_members() {
        global $DB;

        $this->resetAfterTest(true); // Reset all changes automatically after this test.

        $contextid = context_system::instance()->id;

        $cohort = array(
            'contextid' => $contextid,
            'name' => 'cohortnametest1',
            'idnumber' => 'idnumbertest1',
            'description' => 'This is a description for cohort 1'
            );

        $cohort1 = self::getDataGenerator()->create_cohort($cohort);
        // Check the cohorts were correctly created.
        // Todo: this test is covered in the create cohort test - no need to double test.
        $this->assertEquals(1, $DB->count_records_select('cohort', ' (id = :cohortid0)',
            array('cohortid0' => $cohort1->id)));


        $roleid = $this->assignUserCapability('moodle/cohort:assign', $contextid);

        $cohortmembers1 = array(
          'cohorttype' => array('type' => 'id', 'value' => $cohort1->id),
          'usertype' => array('type' => 'id', 'value' => '1')
        );
        // Call the external function.
        $addcohortmembers = core_cohort_external::add_cohort_members(array($cohortmembers1));

        // Check we retrieve the good total number of created cohorts + no error on capability.
        $this->assertEquals(1, count($addcohortmembers));

        foreach ($addcohortmembers as $addcohortmember) {
            $dbcohort = $DB->get_record('cohort_members', array('cohortid' => $cohort1->id));
            $this->assertEquals($dbcohort->cohortid, $cohortmembers1['cohorttype']['value']);
            $this->assertEquals($dbcohort->userid, $cohortmembers1['usertype']['value']);
        }

        // Add a cohort member by username.
        $dbuser = $DB->get_record('user', array('id' => 1));
        $cohortmembers2 = array(
          'cohorttype' => array('type' => 'id', 'value' => $cohort1->id),
          'usertype' => array('type' => 'username', 'value' => $dbuser->username)
        );
        // Call the external function.
        $addcohortmembers = core_cohort_external::add_cohort_members(array($cohortmembers2));

        // Check we retrieve the good total number of created cohorts + no error on capability.
        $this->assertEquals(1, count($addcohortmembers));
        foreach ($addcohortmembers as $addcohortmember) {
            $dbcohort = $DB->get_record('cohort_members', array('cohortid' => $cohort1->id));
            $this->assertEquals($dbcohort->cohortid, $cohortmembers2['cohorttype']['value']);
            $this->assertEquals($dbcohort->userid, $dbuser->id);
        }

        // Add a cohort member by idnumber (for both cohort and user).

        $user = self::getDataGenerator()->create_user(array('idnumber' => 'ExternalCohortAddUser0001'));

        $cohortmembers3 = array(
          'cohorttype' => array('type' => 'idnumber', 'value' => $cohort1->idnumber),
          'usertype' => array('type' => 'idnumber', 'value' => $user->idnumber)
        );
        // Call the external function.
        $addcohortmembers = core_cohort_external::add_cohort_members(array($cohortmembers3));

        // Check we retrieve the good total number of created cohorts + no error on capability.
        $this->assertEquals(1, count($addcohortmembers));
        foreach ($addcohortmembers as $addcohortmember) {
            $dbcohort = $DB->get_record('cohort_members', array('cohortid' => $cohort1->id, 'userid' => $user->id));
            $this->assertEquals($dbcohort->cohortid, $cohortmembers2['cohorttype']['value']);
            $this->assertEquals($dbcohort->userid, $user->id);
        }

        // Call without required capability.
        $cohort4 = array(
            'cohorttype' => array('type' => 'id', 'value' => $cohort1->id),
            'usertype' => array('type' => 'id', 'value' => '2')
            );
        $this->unassignUserCapability('moodle/cohort:assign', $contextid, $roleid);
        $this->setExpectedException('required_capability_exception');
        $addcohortmembers = core_cohort_external::add_cohort_members(array($cohort4));
    }

    /**
     * Test delete_cohort_members
     */
    public function test_delete_cohort_members() {
        global $DB;

        $this->resetAfterTest(true); // Reset all changes automatically after this test.

        $cohort1 = self::getDataGenerator()->create_cohort();
        $user1 = self::getDataGenerator()->create_user();
        $cohort2 = self::getDataGenerator()->create_cohort();
        $user2 = self::getDataGenerator()->create_user();
        $cohort3 = self::getDataGenerator()->create_cohort(array('idnumber' => 'ExternalCohort0001'));

        $user3 = self::getDataGenerator()->create_user(array('idnumber' => 'ExternalCohortUser0001'));

        $context = context_system::instance();
        $roleid = $this->assignUserCapability('moodle/cohort:assign', $context->id);

        $cohortaddmember1 = array(
            'cohorttype' => array('type' => 'id', 'value' => $cohort1->id),
            'usertype' => array('type' => 'id', 'value' => $user1->id)
            );
        $cohortmembers1 = core_cohort_external::add_cohort_members(array($cohortaddmember1));

        $cohortaddmember2 = array(
            'cohorttype' => array('type' => 'id', 'value' => $cohort2->id),
            'usertype' => array('type' => 'id', 'value' => $user2->id)
            );
        $cohortmembers2 = core_cohort_external::add_cohort_members(array($cohortaddmember2));

        // No need to add the cohort/user pair by idnumber since that is tested in the add_cohort test.
        $cohortaddmember3 = array(
          'cohorttype' => array('type' => 'id', 'value' => $cohort3->id),
          'usertype' => array('type' => 'id', 'value' => $user3->id)
        );
        $cohortmembers3 = core_cohort_external::add_cohort_members(array($cohortaddmember3));

        // Check we retrieve no cohorts + no error on capability.
        $this->assertEquals(3, $DB->count_records_select('cohort_members', ' ((cohortid = :idcohort1 AND userid = :iduser1)
            OR (cohortid = :idcohort2 AND userid = :iduser2)
            OR (cohortid = :idcohort3 AND userid = :iduser3)
            )',
            array(
              'idcohort1' => $cohort1->id, 'iduser1' => $user1->id,
              'idcohort2' => $cohort2->id, 'iduser2' => $user2->id,
              'idcohort3' => $cohort3->id, 'iduser3' => $user3->id,
            )));

        // Call the external function.
         $cohortdel1 = array(
            'cohortid' => $cohort1->id,
            'userid' => $user1->id
            );
         $cohortdel2 = array(
            'cohortid' => $cohort2->id,
            'userid' => $user2->id
            );
        core_cohort_external::delete_cohort_members(array($cohortdel1, $cohortdel2));

        // Check we retrieve no cohorts + no error on capability when deleting the first 2 cohorts by ID.
        $this->assertEquals(0, $DB->count_records_select('cohort_members', ' ((cohortid = :idcohort1 AND userid = :iduser1)
            OR (cohortid = :idcohort2 AND userid = :iduser2))',
            array('idcohort1' => $cohort1->id, 'iduser1' => $user1->id, 'idcohort2' => $cohort2->id, 'iduser2' => $user2->id)));

        // Delete the 3rd cohort/member by idnumber.
        $cohortdel2 = array(
          'cohortid' => $cohort3->idnumber,
          'userid' => $user3->idnumber,
          'type' => 'idnumber',
        );

        // Delete the 3rd cohort/member by idnumber as a 2nd argument.
        core_cohort_external::delete_cohort_members(array($cohortdel2), 'idnumber');

        // Check we retrieve no cohorts + no error on capability when deleting the first 2 cohorts by ID.
        $this->assertEquals(0, $DB->count_records_select('cohort_members', ' (cohortid = :idcohort3 AND userid = :iduser3)',
          array('idcohort3' => $cohort3->id, 'iduser3' => $user3->id)));

        // Call without required capability.
        $this->unassignUserCapability('moodle/cohort:assign', $context->id, $roleid);
        $this->setExpectedException('required_capability_exception');
        core_cohort_external::delete_cohort_members(array($cohortdel1, $cohortdel2));
    }
}
