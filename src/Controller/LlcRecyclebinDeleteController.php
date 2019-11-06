<?php

namespace Drupal\llc_recyclebin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 *
 */
class LlcRecyclebinDeleteController extends ControllerBase {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   *
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_route_match')
    );
  }

  /**
   *
   */
  public function entityDelete() {
    $parameters = $this->routeMatch->getParameters();
    foreach ($parameters as $entity_type_id => $entity_id) {
      $entity = entity_load($entity_type_id, $entity_id);
      $state_item = $entity->get('field_state')->first();
      $state_item->applyTransitionById('delete');
      $entity->setPublished(FALSE);
      $entity->set('moderation_state', "draft");
      $entity->save();
    }
    return $this->redirect($this->getRedirectUrl($entity)->getRouteName(), $this->getRedirectUrl($entity)->getRouteParameters());
  }

  /**
   *
   */
  protected function getRedirectUrl($entity) {
    if ($entity->hasLinkTemplate('collection')) {
      // If available, return the collection URL.
      return $entity->urlInfo('collection');
    }
    else {
      // Otherwise fall back to the front page.
      return Url::fromRoute('<front>');
    }
  }

}
