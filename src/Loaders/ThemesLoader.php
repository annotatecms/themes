<?php
/**
 * Created by PhpStorm.
 * User: Michal
 * Date: 8.1.14
 * Time: 20:11
 */

namespace AnnotateCms\Themes\Loaders;

use AnnotateCms\Framework\Diagnostics\CmsPanel;
use AnnotateCms\Framework\Templating\ITemplateFactory;
use AnnotateCms\Themes\Exceptions\ThemeNotFoundException;
use AnnotateCms\Themes\Theme;
use Kdyby\Events\Subscriber;
use Nette\Diagnostics\Dumper;
use Nette\Object;
use Nette\Templating\IFileTemplate;
use Nette\Templating\ITemplate;
use Nette\Utils\Finder;
use Nette\Utils\Neon;

if (!defined("THEMES_DIR")) {
    define("THEMES_DIR", APP_DIR . DS . "addons" . DS . "themes" . DS);
}


/**
 * Class ThemesLoader
 * @package AnnotateCms\Themes\Loaders
 *
 * @method onActivateTheme(Theme $activeTheme)
 */
class ThemesLoader extends Object implements Subscriber
{
    const classname = __CLASS__;

    public $onActivateTheme = array();

    private $themes = array();

    private $frontendTheme;

    private $backendTheme;

    /** @var  Theme */
    private $activeTheme;

    function __construct()
    {
        $this->themes = $this->load();
    }

    private function load()
    {
        $themes = array();

        foreach (Finder::findFiles("*theme.neon")->from(\THEMES_DIR) as $path => $file) {
            $neon = Neon::decode(\file_get_contents($path));
            $aDir = \dirname($path);
            $themes[$neon["name"]] = new Theme($neon, $aDir);

        }

        return $themes;
    }

    public function setFrontendTheme($name)
    {
        $this->frontendTheme = $name;
    }

    public function setBackendTheme($name)
    {
        $this->backendTheme = $name;
    }

    public function activateFrontendTheme()
    {
        $this->activeTheme = $this->getTheme($this->frontendTheme);
        $this->onActivateTheme($this->activeTheme);
        $this->addDebugSection();
    }

    public function activateBackendTheme()
    {
        $this->activeTheme = $this->getTheme($this->backendTheme);
        $this->onActivateTheme($this->activeTheme);
        $this->addDebugSection();
    }

    private function getTheme($name)
    {
        if (isset($this->themes[$name])) {
            return $this->themes[$name];
        }
        throw new ThemeNotFoundException("Theme '$name' not found");
    }

    public function getSubscribedEvents()
    {
        return array(
            'AnnotateCms\Framework\Templating\TemplateFactory::onSetupTemplate',
            'AnnotateCms\Framework\Templating\TemplateFactory::onLoadTemplate',
            'AnnotateCms\Framework\Templating\TemplateFactory::onLoadLayout',
            'AnnotateCms\Framework\Templating\TemplateFactory::onCreateFormTemplate',
        );
    }

    public function onSetupTemplate(ITemplate $template)
    {
        if (!$this->activeTheme) {
            return;
        }
        $template->theme = $this->activeTheme;
        $template->themeDir = $template->basePath . "/" . $this->activeTheme->getRelativePath() . "/";
    }

    public function onLoadTemplate(ITemplateFactory $templateFactory, $templateFile, $presenterName)
    {

        if (!$this->activeTheme) {
            return;
        }

        $templateFactory->addTemplate(
            $this->formatTemplateFilePath($templateFile, $presenterName)
        );
        $templateFactory->addTemplate(
            $this->formatTemplateFilePath($templateFile)
        );
    }

    public function onLoadLayout(ITemplateFactory $templateFactory, $layoutFile, $presenterName)
    {
        if (!$this->activeTheme) {
            return;
        }

        $templateFactory->addLayout(
            $this->formatTemplateFilePath($layoutFile, $presenterName)
        );
        $templateFactory->addLayout(
            $this->formatTemplateFilePath($layoutFile)
        );
    }

    public function onCreateFormTemplate($fileName, IFileTemplate $template)
    {
        $path = $this->activeTheme->getPath() . DS . "components" . DS . $fileName;
        if(file_exists($path)) {
            $template->setFile($path);
        }
    }

    private function formatTemplateFilePath($templateFile, $presenterName = null)
    {
        $base = $this->activeTheme->getPath() . DS . "templates" . DS;
        if ($presenterName) {
            return $base . $presenterName . DS . $templateFile . ".latte";
        } else {
            return $base . $templateFile . ".latte";
        }
    }

    private function addDebugSection()
    {
        $theme = array(
            "name" => $this->activeTheme->getName(),
            "version" => $this->activeTheme->getVersion(),
            "author" => $this->activeTheme->getAuthor(),
            "dependencies" => $this->activeTheme->getDependencies(),
        );
        CmsPanel::$sections[] = function () use ($theme) {
            $html = "<h2>Loaded Theme:</h2>";
            $html .= "<div><table>";
            $html .= "<thead><tr><th>Name</th><th>Version</th><th>Author</th><th>Deps</th></tr></thead>";
            $html .= "<tr><td>" . $theme["name"] . "</td><td>" . $theme["version"] . "</td><td>" . $theme["author"] . "</td><td>" . Dumper::toHtml($theme["dependencies"], array(Dumper::COLLAPSE => true)) . "</td></tr>";
            $html .= "</table></div>";
            return $html;
        };
    }

} 