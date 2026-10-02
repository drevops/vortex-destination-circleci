<?php

declare(strict_types=1);

namespace Drupal\Tests\sdc_devel\Kernel;

use Drupal\sdc_devel\TwigValidator\TwigVariableCollectorVisitor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the TwigVariableCollectorVisitor.
 *
 * @phpcs:disable Drupal.Commenting.FunctionComment.Missing
 *
 * @internal
 */
#[CoversClass(TwigVariableCollectorVisitor::class)]
#[Group('sdc_devel')]
#[RunTestsInSeparateProcesses]
final class TwigVariableCollectorTest extends TwigValidatorTestBase {

  public function testTwigValidatorCollector(): void {
    $component_id = 'sdc_devel_theme_test:collector';
    $component = $this->componentPluginManager->find($component_id);

    $this->twigValidator->validateComponent($component_id, $component);
    $errors = $this->twigValidator->getMessages();

    self::assertCount(1, $errors, 'Error count do not match');

    self::assertSame('Unused variables: zoo_unused, item_unused, my_attributes, macro_unused_1, macro_unused_2, macro_unused_3, macro_unused_4, test_slot_string, test_slot_block, test_prop_bool', (string) $errors[0]->message());
  }

}
