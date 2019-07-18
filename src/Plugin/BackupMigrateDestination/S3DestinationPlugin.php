<?php

namespace Drupal\backup_migrate\Plugin\BackupMigrateDestination;

use BackupMigrate\Drupal\EntityPlugins\DestinationPluginBase;

/**
 * Defines a file directory destination plugin.
 *
 * @BackupMigrateDestinationPlugin(
 *   id = "S3",
 *   title = @Translation("Amazon S3"),
 *   description = @Translation("Back up to a Amazon S3."),
 *   wrapped_class = "\BackupMigrate\Drupal\Destination\DrupalS3Destination"
 * )
 */
class S3DestinationPlugin extends DestinationPluginBase {

}
