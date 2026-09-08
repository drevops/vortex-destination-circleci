<?php

namespace Drupal\fast404;

use Symfony\Component\HttpFoundation\Request;

/**
 * Fast404: A value object for manager Fast 404 logic.
 *
 * @package Drupal\fast404
 */
interface Fast404FactoryInterface {

  /**
   * Creates a pre-configured instance of Fast404.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The request to get the 404 page for.
   *
   * @return \Drupal\fast404\Fast404
   *   A fully configured Fast404 instance.
   */
  public function createInstance(?Request $request = NULL);

}
