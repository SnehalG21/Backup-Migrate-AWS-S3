# Backup and Migrate for Drupal 8

The Drupal 8 rebuild of Backup and Migrate (WIP)

## Installation

This module uses composer to manage dependencies. To install from this repository:

Clone the repository into your Drupal site modules directory:

`git clone git@github.com:backupmigrate/backup_migrate_drupal.git /path/to/site/modules/backup_migrate`

Change to the module directory:

`cd /path/to/site/modules/backup_migrate`

Install using composer

`composer install`

Local file encryption via php-encrpytion lib:

If you require local file encryption support you will also need to install the php-encryption library( https://github.com/defuse/php-encryption ):
`composer require defuse/php-encryption`

Install the module as usual using Drush or the Drupal UI.

For more information on using composer see: https://getcomposer.org/

To upload backup on AWS S3 :

`1. Add S3 destination : /admin/config/development/backup_migrate/settings/destination. Select Amazon S3`

`2. Enter details needed to create S3 client.`

`3. Store secret access key and access id in settings.php.
    $settings['backup_migrate_aws_access_key'] = 'key';
    $settings['backup_migrate_aws_key_id'] = 'id';`

 `4. Now you can select backup destination just created to store backup.`

Note: To use AWS S3 as a backup storage, this module requires `aws-sdk-php` library. So make sure this library is present.
