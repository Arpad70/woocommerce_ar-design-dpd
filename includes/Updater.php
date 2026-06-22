<?php

declare(strict_types=1);

namespace ArDesign\DPD;

use ArDesign\Shared\Updates\GitHubPluginUpdater as BaseGitHubPluginUpdater;

if (! defined('ABSPATH')) {
    exit;
}

require_once WP_PLUGIN_DIR . '/ar-design-shared-support/includes/updates/GitHubPluginUpdater.php';

final class ArDesignDpdUpdater extends BaseGitHubPluginUpdater
{
    public function __construct(string $repositoryFullName, string $pluginBasename, string $currentVersion)
    {
        parent::__construct(
            $repositoryFullName,
            $pluginBasename,
            $currentVersion,
            array(
                'plugin_slug' => 'ar-design-dpd',
                'plugin_name' => 'AR Design DPD for WooCommerce',
                'text_domain' => 'ar-design-dpd',
                'description' => 'Samostatný DPD modul pre WooCommerce spravovaný AR Design.',
                'author_label' => 'AR Design',
                'user_agent_slug' => 'ar-design-dpd',
                'cache_key_prefix' => 'ar_design_dpd_release_data_',
                'preferred_zip_names' => array('ar-design-dpd.zip'),
                'preferred_zip_prefixes' => array('ar-design-dpd-'),
                'allow_any_zip_fallback' => false,
            )
        );
    }
}
