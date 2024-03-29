<?php

namespace Drupal\backup_migrate\Controller;

use BackupMigrate\Core\Destination\ListableDestinationInterface;
use BackupMigrate\Drupal\Destination\DrupalBrowserDownloadDestination;
use Drupal\backup_migrate\Entity\Destination;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Class BackupController.
 *
 * @package Drupal\backup_migrate\Controller
 */
class BackupController extends ControllerBase {

  /**
   * List all backups stored in various destinations.
   */
  public function listAll() {
    $storage = \Drupal::entityTypeManager()
      ->getStorage('backup_migrate_destination');

    $out = [];
    foreach ($storage->getQuery()->execute() as $key) {
      $entity = $storage->load($key);
      $destination = $entity->getObject();
      $label = $destination->confGet('name');

      if ($entity->type == 'S3') {
        $out[$key] = [
          'title' => [
            '#markup' => '<h2>' . $this->t('Most recent backups in %dest', ['%dest' => $label]) . '</h2>',
          ],
          'list' => $this::listAWSDestinationBackups($destination, $key, 5),
        ];
      }
      else {
        $out[$key] = [
          'title' => [
            '#markup' => '<h2>' . $this->t('Most recent backups in %dest', ['%dest' => $label]) . '</h2>',
          ],
          'list' => $this::listDestinationBackups($destination, $key, 5),
        ];

      }

      // Add the more link.
      if ($entity->access('backups') && $entity->hasLinkTemplate('backups')) {
        $out[$key]['link'] = $entity->toLink(
          $this->t('View all backups in %dest', ['%dest' => $label]), 'backups'
        )->toRenderable();
      }

    }
    return $out;
  }

  /**
   * Get the title for the listing page of a destination entity.
   *
   * @param \Drupal\backup_migrate\Entity\Destination $backup_migrate_destination
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function listDestinationEntityBackupsTitle(Destination $backup_migrate_destination) {
    return $this->t('Backups in @destination_name',
      ['@destination_name' => $backup_migrate_destination->label()]);
  }

  /**
   * List the backups in the given destination.
   *
   * @param \Drupal\backup_migrate\Entity\Destination $backup_migrate_destination
   *
   * @return mixed
   */
  public function listDestinationEntityBackups(Destination $backup_migrate_destination) {
    $destination = $backup_migrate_destination->getObject();
    if ($backup_migrate_destination->type == 'S3') {
      return $this->listAWSDestinationBackups($destination,
            $backup_migrate_destination->id());
    }
    else {
      return $this->listDestinationBackups($destination,
            $backup_migrate_destination->id());
    }

  }

  /**
   * List the backups in the given destination.
   *
   * @param \BackupMigrate\Core\Destination\ListableDestinationInterface $destination
   * @param string $backup_migrate_destination_id
   * @param int $count
   *
   * @return mixed
   */
  public function listDestinationBackups(
    ListableDestinationInterface $destination,
    $backup_migrate_destination_id,
    $count = NULL
  ) {

    // Get a sorted list of files.
    $rows = [];
    $header = [
      [
        'data' => $this->t('Name'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        'field' => 'name',
      ],
      [
        'data' => $this->t('Date'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        'field' => 'datestamp',
        'sort' => 'desc',
      ],
      [
        'data' => $this->t('Size'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        'field' => 'filesize',
        'sort' => 'desc',
      ],
      [
        'data' => $this->t('Operations'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    $order = tablesort_get_order($header);
    $sort = tablesort_get_sort($header);
    $php_sort = $sort == 'desc' ? SORT_DESC : SORT_ASC;

    $backups = $destination->queryFiles([], $order['sql'], $php_sort, $count);

    foreach ($backups as $backup_id => $backup) {
      $rows[] = [
        'data' => [
          // Cells.
          $backup->getFullName(),
          \Drupal::service('date.formatter')
            ->format($backup->getMeta('datestamp')),
          format_size($backup->getMeta('filesize')),
          [
            'data' => [
              '#type' => 'operations',
              '#links' => [
                'restore' => [
                  'title' => $this->t('Restore'),
                  'url' => Url::fromRoute(
                    'entity.backup_migrate_destination.backup_restore',
                    [
                      'backup_migrate_destination' => $backup_migrate_destination_id,
                      'backup_id' => $backup_id,
                    ]
                  ),
                ],
                'download' => [
                  'title' => $this->t('Download'),
                  'url' => Url::fromRoute(
                    'entity.backup_migrate_destination.backup_download',
                    [
                      'backup_migrate_destination' => $backup_migrate_destination_id,
                      'backup_id' => $backup_id,
                    ]
                  ),
                ],
                'delete' => [
                  'title' => $this->t('Delete'),
                  'url' => Url::fromRoute(
                    'entity.backup_migrate_destination.backup_delete',
                    [
                      'backup_migrate_destination' => $backup_migrate_destination_id,
                      'backup_id' => $backup_id,
                    ]
                  ),
                ],
              ],
            ],
          ],
        ],
      ];
    }

    $build['backups_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('There are no backups in this destination.'),
    ];

    return $build;
  }

  /**
   * @param \BackupMigrate\Core\Destination\ListableDestinationInterface $destination
   * @param $backup_migrate_destination_id
   * @param null $count
   *
   * @return mixed
   */
  public function listAWSDestinationBackups(
    ListableDestinationInterface $destination,
    $backup_migrate_destination_id,
    $count = NULL
  ) {

      // Get a sorted list of files.
    $rows = [];
    $header = [
          [
            'data' => $this->t('Name'),
            'class' => [RESPONSIVE_PRIORITY_MEDIUM],
            'field' => 'name',
          ],
          [
            'data' => $this->t('Date'),
            'class' => [RESPONSIVE_PRIORITY_MEDIUM],
            'field' => 'datestamp',
            'sort' => 'desc',
          ],
          [
            'data' => $this->t('Size'),
            'class' => [RESPONSIVE_PRIORITY_MEDIUM],
            'field' => 'filesize',
            'sort' => 'desc',
          ],
          [
            'data' => $this->t('Operations'),
            'class' => [RESPONSIVE_PRIORITY_LOW],
          ],
    ];

    if ($s3client = $destination->getClient()) {

      $files = [];

      try {
        $results = $s3client->getPaginator('ListObjects', [
          'Bucket' => $destination->confGet('s3_bucket'),
        ]);

        foreach ($results as $result) {
          foreach ($result['Contents'] as $object) {
            $files[] = $object;
          }
        }
      }
      catch (S3Exception $e) {
        echo $e->getMessage() . PHP_EOL;
      }

      $files = array_reverse(array_slice($files, -$count, $count, TRUE));

      foreach ($files as $backup) {
        $timestamp = $backup['LastModified']->getTimestamp();
        $rows[] = [
          'data' => [
                  // Cells.
            $backup['Key'],
            date('D, m/d/Y - H:i', $timestamp),
            format_size($backup['Size']),
                  [
                    'data' => [
                      '#type' => 'operations',
                      '#links' => [
                        'restore' => [
                          'title' => $this->t('Restore'),
                          'url' => Url::fromRoute(
                                      'entity.backup_migrate_destination.backup_restore',
                                      [
                                        'backup_migrate_destination' => $backup_migrate_destination_id,
                                        'backup_id' => $backup['Key'],
                                      ]
                          ),
                        ],
                        'download' => [
                          'title' => $this->t('Download'),
                          'url' => Url::fromRoute(
                                      'entity.backup_migrate_destination.backup_download',
                                      [
                                        'backup_migrate_destination' => $backup_migrate_destination_id,
                                        'backup_id' => $backup['Key'],
                                      ]
                          ),
                        ],
                        'delete' => [
                          'title' => $this->t('Delete'),
                          'url' => Url::fromRoute(
                                      'entity.backup_migrate_destination.backup_delete',
                                      [
                                        'backup_migrate_destination' => $backup_migrate_destination_id,
                                        'backup_id' => $backup['Key'],
                                      ]
                          ),
                        ],
                      ],
                    ],
                  ],
          ],
        ];
      }

      $build['backups_table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('There are no backups in this destination.'),
      ];
    }
    else {
      $build['backups_table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
      return $build;

    }
    return $build;
  }

  /**
   * Download a backup via the browser.
   *
   * @param \Drupal\backup_migrate\Entity\Destination $backup_migrate_destination
   * @param $backup_id
   */
  public function download(Destination $backup_migrate_destination, $backup_id) {
    $destination = $backup_migrate_destination->getObject();

    if ($destination->confGet('host') == 's3.amazonaws.com') {
      $s3client = $destination->getClient();
      $file = $destination->getFile($backup_id);
      $object = $s3client->getObject([
        'Bucket' => $destination->confGet('s3_bucket'),
        'Key'    => $file->getMeta('Key'),
      ]);

      header("Content-Type: {$object['ContentType']}");
      echo $object['Body'];
    }
    else {
      $file = $destination->getFile($backup_id);
      $file = $destination->loadFileForReading($file);
      $browser = new DrupalBrowserDownloadDestination();
      $browser->saveFile($file);
    }

  }

}
