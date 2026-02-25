<?php
namespace AIWA;

use AIWA\Infrastructure\Admin\SettingsPage;
use AIWA\Infrastructure\Admin\ToolsPage;
use AIWA\Infrastructure\Rest\Routes;
use AIWA\Infrastructure\Admin\EditorSidebar;

final class Plugin {
  public function register(): void {
    (new SettingsPage())->register();
    (new ToolsPage())->register();
    (new Routes())->register();
  }
}
public function register(): void {
  (new SettingsPage())->register();
  (new ToolsPage())->register();
  (new EditorSidebar())->register();
  (new Routes())->register();
}