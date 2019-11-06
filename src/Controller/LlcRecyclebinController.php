<?php

namespace Drupal\llc_recyclebin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\multiversion\MultiversionManagerInterface;
use Drupal\node\Entity\Node;

/**
 *
 */
class LlcRecyclebinController extends ControllerBase {

  /**
   * The entity query object.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The entity manager service.
   *
   * @var \Drupal\multiversion\MultiversionManagerInterface
   */
  protected $multiversionManager;

  /**
   * Constructs an LlcRecyclebinController object.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query object.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter service.
   * @param \Drupal\multiversion\MultiversionManagerInterface $entity_manager
   *   The Multiversion manager.
   */
  public function __construct(QueryFactory $entity_query, DateFormatter $date_formatter, MultiversionManagerInterface $multiversion_manager) {
    $this->entityQuery = $entity_query;
    $this->dateFormatter = $date_formatter;
    $this->multiversionManager = $multiversion_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query'),
      $container->get('date.formatter'),
      $container->get('multiversion.manager')
    );
  }

  /**
   *
   */
  public function entityList($entity_type_id = NULL) {
    $entity_type_id = 'node';
    $entities = $this->loadEntities($entity_type_id);
    $rows = [];

    foreach ($entities as $entity) {
      if ($entity instanceof ContentEntityInterface) {
        $id = $entity->id();
        $rows[$id] = [];
        $rows[$id]['id'] = $id;
        $rows[$id]['label'] = [
          'data' => [
            '#type' => 'link',
            '#title' => $entity->label(),
            '#access' => $entity->access('view'),
            '#url' => $entity->urlInfo(),
          ],
        ];

        if (in_array($entity_type_id, ['node', 'comment'])) {
          $rows[$id]['created'] = $this->dateFormatter->format($entity->getCreatedTime(), 'short');
          $rows[$id]['changed'] = $this->dateFormatter->format($entity->getChangedTimeAcrossTranslations(), 'short');
          $rows[$id]['owner'] = $entity->getOwner()->label();
        }
      }
    }

    $entity_types = $this->multiversionManager->getEnabledEntityTypes();
    global $base_url;
    return [
      '#type' => 'table',
      '#header' => $this->header($entity_type_id),
      '#rows' => $rows,
      '#empty' => $this->t('The @label recyclebin is empty.', ['@label' => $entity_types[$entity_type_id]->get('label')]),
    ];
  }

  /**
   *
   */
  protected function loadEntities($entity_type_id = NULL) {
    if (!empty($entity_type_id)) {
      $entity_query = $this->entityQuery->get($entity_type_id);
      $entity_query->condition('field_state', 'deleted');
      $entity_query->pager(50);
      if (in_array($entity_type_id, ['node', 'comment'])) {
        $entity_query->tableSort($this->header($entity_type_id));
      }
      $entity_ids = $entity_query->execute();

      /** @var \Drupal\multiversion\Entity\Storage\ContentEntityStorageInterface $storage */
      $storage = $this->entityTypeManager()->getStorage($entity_type_id);
      return $storage->loadMultiple($entity_ids);
    }
  }

  /**
   *
   */
  protected function header($entity_type_id = NULL) {
    $header = [];
    $header['id'] = [
      'data' => $this->t('Id'),
    ];
    $header['label'] = [
      'data' => $this->t('Name'),
    ];
    if (in_array($entity_type_id, ['node', 'comment'])) {
      $header['created'] = [
        'data' => $this->t('Created'),
        'field' => 'created',
        'specifier' => 'created',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ];
      $header['changed'] = [
        'data' => $this->t('Updated'),
        'field' => 'changed',
        'specifier' => 'changed',
        'sort' => 'asc',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ];
      $header['owner'] = [
        'data' => $this->t('Owner'),
      ];
    }
    return $header;
  }

  /**
   *
   */
  public function clearAll($entity_type_id = NULL) {
    $entity_type_id = 'node';
    if (!empty($entity_type_id)) {
      $entity_query = $this->entityQuery->get($entity_type_id);
      $entity_query->condition('field_state', 'deleted');
      $entity_query->pager(50);
      if (in_array($entity_type_id, ['node', 'comment'])) {
        $entity_query->tableSort($this->header($entity_type_id));
      }
      $entity_ids = $entity_query->execute();

      foreach ($entity_ids as $nid) {
        $node = Node::load($nid);
        $node->delete();
      }
    }
    return $this->redirect('<front>');
  }

}
