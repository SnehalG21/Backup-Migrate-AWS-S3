<?php

namespace BackupMigrate\Core\Destination;

/**
 * @file
 * S3Destination for AWS S3.
 */


use Aws\S3\S3Client;
use BackupMigrate\Core\Config\ConfigurableInterface;
use BackupMigrate\Core\Exception\BackupMigrateException;
use BackupMigrate\Core\File\BackupFile;
use BackupMigrate\Core\File\BackupFileInterface;
use BackupMigrate\Core\File\BackupFileReadableInterface;
use BackupMigrate\Core\File\ReadableStreamBackupFile;
use Drupal\Core\Site\Settings;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Class S3Destination.
 *
 * @package BackupMigrate\Core\Destination
 */
class S3Destination extends DestinationBase implements RemoteDestinationInterface, ListableDestinationInterface, ReadableDestinationInterface, ConfigurableInterface {

  use MessengerTrait;

  /**
   * Stores client.
   *
   * @var \Aws\S3\S3Client
   */
  protected $client = NULL;

  /**
   * {@inheritdoc}
   */
  public function checkWritable() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function _deleteFile($id) {
    // Delete an object from the bucket.
    $this->getClient()->deleteObject([
      'Bucket' => $this->confGet('s3_bucket'),
      'Key'    => $id,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function _idToPath($id) {
    return rtrim($this->confGet('directory'), '/') . '/' . $id;
  }

  /**
   * {@inheritdoc}
   */
  protected function _saveFile(BackupFileReadableInterface $file) {
    $this->backupFilesAWS($this->getClient(), $file->getFullName(), $file->realpath());
  }

  /**
   * {@inheritdoc}
   */
  protected function _saveFileMetadata(BackupFileInterface $file) {
    // Metadata is saved during the file upload process. Nothing to do here.
  }

  /**
   * {@inheritdoc}
   */
  protected function _loadFileMetadataArray(BackupFileInterface $file) {
    // Metadata is fetched with the listing. There is nothing to be fetched.
  }

  /**
   * {@inheritdoc}
   */
  public function getFile($id) {
    // There is no way to fetch file info for a single file so we load them all.
    $files = $this->listFiles();
    if (isset($files[$id])) {
      return $files[$id];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function loadFileForReading(BackupFileInterface $file) {
    // If this file is already readable, simply return it.
    if ($file instanceof BackupFileReadableInterface) {
      return $file;
    }
    $id = $file->getMeta('id');
    if ($this->fileExists($id)) {
      $s3client = $this->getClient();
      // Fetch object using getObject().
      // Using SaveAs store in temp file named backup.gz.
      $s3client->getObject([
        'Bucket' => $this->confGet('s3_bucket'),
        'Key'    => $file->getMeta('Key'),
        'SaveAs' => '/tmp/backup.gz',
      ]);
      return new ReadableStreamBackupFile($this->_idToPath('/tmp/backup.gz'));
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function fileExists($id) {
    return (boolean) $this->getFile($id);
  }

  /**
   * {@inheritdoc}
   */
  public function listFiles($count = 100, $start = 0) {
    $file_list = [];
    $iterator = $this->getClient()->getIterator('ListObjects', ['Bucket' => $this->confGet('s3_bucket')]);
    foreach ($iterator as $object) {
      $file_list[] = $object;
    }

    $files = [];
    foreach ($file_list as $file) {
      $filename = !empty($file['filename']) ? $file['filename'] : $file['Key'];
      $out = new BackupFile();
      $out->setMeta('id', $filename);
      $out->setMetaMultiple($file);
      $out->setFullName($filename);
      $files[$filename] = $out;
    }

    return $files;
  }

  /**
   * {@inheritdoc}
   */
  public function countFiles() {
    $file_list = $this->getClient()->listBackups();
    return count($file_list);
  }

  /**
   * {@inheritdoc}
   */
  public function getClient() {
    if ($this->client == NULL) {
      if ($this->confGet('region') && $this->confGet('s3_bucket') && $this->confGet('host')) {
        $aws_id = Settings::get('backup_migrate_aws_key_id');
        $aws_key = Settings::get('backup_migrate_aws_access_key');
        if ($aws_id && $aws_key) {
          $this->client = new S3Client([
            'version' => 'latest',
            'region' => $this->confGet('region'),
            'credentials' => [
              'key' => $aws_id,
              'secret' => $aws_key,
            ],
          ]);
        }
        else {
          $this->messenger()->addError(t('You must enter Secret key and Key id to use AWS S3.'));
        }
      }
      else {
        $this->messenger()->addError(t('Please fill all mandatory fields to create S3 client'));
        return $this->client;
      }

    }
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  public function queryFiles($filters = [], $sort = 'datestamp', $sort_direction = SORT_DESC, $count = 100, $start = 0) {

    // Get the full list of files.
    $out = $this->listFiles($count + $start);
    foreach ($out as $key => $file) {
      $out[$key] = $this->loadFileMetadata($file);
    }

    // Filter the output.
    $out = array_reverse($out);
    // Slice the return array.
    if ($count || $start) {
      $out = array_slice($out, $start, $count);
    }

    return $out;
  }

  /**
   * To upload backup on AWS S3.
   */
  protected function backupFilesAWS(S3Client $client, $filename, $file_loc) {
    try {
      $bucket = $this->confGet('s3_bucket');
      // Use putObject() to upload object into bucket.
      $result = $client->putObject([
        'Bucket' => $bucket,
        'Key' => $filename,
        'SourceFile' => $file_loc,
      ]);
      $this->messenger()->addStatus('Your backup has been saved to your S3 account.' . $result['ObjectURL']);
      return $result;
    }
    catch (BackupMigrateException $e) {
      throw new BackupMigrateException(
         'Could not upload to S3: %err (code: %code)',
            ['%err' => $e->getMessage(), '%code' => $e->getCode()]
      );
    }
  }

}
