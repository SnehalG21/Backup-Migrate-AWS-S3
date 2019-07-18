<?php

namespace BackupMigrate\Drupal\Destination;

/**
 * @file
 * File to define schema for AWS S3.
 */

use BackupMigrate\Core\Destination\S3Destination;
use Drupal;

/**
 * Class DrupalS3Destination.
 *
 * @package BackupMigrate\Drupal\Destination
 */
class DrupalS3Destination extends S3Destination {

  /**
   * Init configurations.
   */
  public function configSchema($params = []) {
    $schema = [];

    // Init settings.
    if ($params['operation'] == 'initialize') {
      $schema['fields']['host'] = [
        'type' => 'text',
        'title' => t('Host'),
        'default' => 's3.amazonaws.com',
        'required' => TRUE,
        'description' => t('Enter Host name. For e.g. <i>s3.amazonaws.com</i>'),
      ];

      $schema['fields']['s3_bucket'] = [
        'type' => 'text',
        'title' => t('S3 Bucket'),
        'required' => TRUE,
        'description' => t('This bucket must already exist. It will not be created for you.'),
      ];

      $schema['fields']['region'] = [
        'type' => 'text',
        'title' => t('Region'),
        'description' => t('Enter region. For e.g. <i>ap-south-1</i>'),
      ];

      Drupal::messenger()->addMessage('Please store Access Key Id and Secret Access Key in settings.php. Add access key like $settings[backup_migrate_aws_access_key] = key and $settings[backup_migrate_aws_key_id] = id.', 'warning');

    }

    return $schema;
  }

}
