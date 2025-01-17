<?php

/*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */


namespace api\v4\Entity;

use Civi\Api4\Contact;
use Civi\Api4\OptionValue;
use api\v4\Api4TestBase;

/**
 * @group headless
 */
class ContactJoinTest extends Api4TestBase {

  public function testContactJoin() {
    $contact = $this->createTestRecord('Contact', [
      'first_name' => uniqid(),
      'last_name' => uniqid(),
    ]);
    $entitiesToTest = ['Address', 'OpenID', 'IM', 'Website', 'Email', 'Phone'];

    foreach ($entitiesToTest as $entity) {
      $this->createTestRecord($entity, [
        'contact_id' => $contact['id'],
      ]);
      $results = civicrm_api4($entity, 'get', [
        'where' => [['contact_id', '=', $contact['id']]],
        'select' => ['contact_id.*_name', 'contact_id.id'],
      ]);
      foreach ($results as $result) {
        $this->assertEquals($contact['id'], $result['contact_id.id']);
        $this->assertEquals($contact['display_name'], $result['contact_id.display_name']);
      }
    }
  }

  public function testJoinToPCMWillReturnArray() {
    $contact = $this->createTestRecord('Contact', [
      'preferred_communication_method' => [1, 2, 3],
      'contact_type' => 'Individual',
      'first_name' => 'Test',
      'last_name' => 'PCM',
    ]);

    $fetchedContact = Contact::get()
      ->addWhere('id', '=', $contact['id'])
      ->addSelect('preferred_communication_method')
      ->execute()
      ->first();

    $this->assertCount(3, $fetchedContact["preferred_communication_method"]);
  }

  public function testJoinToPCMOptionValueWillShowLabel() {
    $options = OptionValue::get()
      ->addWhere('option_group_id:name', '=', 'preferred_communication_method')
      ->execute()
      ->getArrayCopy();

    $optionValues = array_column($options, 'value');
    $labels = array_column($options, 'label');

    $contact = $this->createTestRecord('Contact', [
      'preferred_communication_method' => $optionValues,
      'contact_type' => 'Individual',
      'first_name' => 'Test',
      'last_name' => 'PCM',
    ]);

    $contact2 = $this->createTestRecord('Contact', [
      'preferred_communication_method' => $optionValues,
      'contact_type' => 'Individual',
      'first_name' => 'Test',
      'last_name' => 'PCM2',
    ]);

    $contactIds = array_column([$contact, $contact2], 'id');

    $fetchedContact = Contact::get()
      ->addWhere('id', 'IN', $contactIds)
      ->addSelect('preferred_communication_method:label')
      ->execute()
      ->first();

    $this->assertEquals($labels, $fetchedContact['preferred_communication_method:label']);
  }

}
