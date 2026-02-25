<?php
namespace AIWA;

use AIWA\Infrastructure\Admin\SettingsPage;
use AIWA\Infrastructure\Admin\ToolsPage;
use AIWA\Infrastructure\Admin\EditorSidebar;
use AIWA\Infrastructure\Rest\Routes;

/**
 * Registers plugin integrations (admin UI + REST).
 */
final class Plugin {
  public function register(): void {
    (new SettingsPage())->register();
    (new ToolsPage())->register();
    (new EditorSidebar())->register();
    (new Routes())->register();
  }
}
