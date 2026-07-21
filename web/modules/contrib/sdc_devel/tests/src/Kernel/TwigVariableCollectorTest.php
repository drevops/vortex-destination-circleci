<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel;

/**
 * @coversDefaultClass \Drupal\sdc_devel\src\TwigValidator\TwigVariableCollectorVisitor
 *
 * @group sdc_devel
 * @internal
 *
 * @phpcs:disable Drupal.Commenting.FunctionComment.Missing
 */
final class TwigVariableCollectorTest extends TwigValidatorTestBase {

  public function testTwigValidatorCollector(): void {
    $component_id = 'sdc_devel_theme_test:collector';
    $component = $this->componentPluginManager->find($component_id);

    $this->twigValidator->validateComponent($component_id, $component);
    $errors = $this->twigValidator->getMessages();

    $this->assertSame(1, \count($errors), 'Error count do not match');

    $this->assertSame('Unused variables: zoo_unused, item_unused, my_attributes, macro_unused_1, macro_unused_2, macro_unused_3, macro_unused_4, test_slot_string, test_slot_block, test_prop_bool', (string) $errors[0]->message());
  }

}
