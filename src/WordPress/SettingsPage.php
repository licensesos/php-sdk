<?php

declare(strict_types=1);

namespace LicensesOS\WordPress;

/**
 * WordPress Settings Page helper for license management.
 *
 * Provides a ready-to-use settings page for managing licenses.
 * Extend this class and customize as needed.
 *
 * Usage:
 * ```php
 * class MyPluginSettings extends SettingsPage {
 *     protected function getLicenseManager(): LicenseManager {
 *         return MyPluginLicense::getInstance();
 *     }
 *
 *     protected function getPageTitle(): string {
 *         return 'My Plugin License';
 *     }
 *
 *     protected function getMenuTitle(): string {
 *         return 'License';
 *     }
 *
 *     protected function getMenuSlug(): string {
 *         return 'my-plugin-license';
 *     }
 * }
 *
 * // In your plugin's init hook
 * add_action('admin_menu', [new MyPluginSettings(), 'addMenuPage']);
 * ```
 */
abstract class SettingsPage
{
    /**
     * Get the license manager instance.
     */
    abstract protected function getLicenseManager(): LicenseManager;

    /**
     * Get the page title.
     */
    abstract protected function getPageTitle(): string;

    /**
     * Get the menu title.
     */
    abstract protected function getMenuTitle(): string;

    /**
     * Get the menu slug.
     */
    abstract protected function getMenuSlug(): string;

    /**
     * Get the parent menu slug (default: options-general.php for Settings menu).
     */
    protected function getParentSlug(): string
    {
        return 'options-general.php';
    }

    /**
     * Get the required capability.
     */
    protected function getCapability(): string
    {
        return 'manage_options';
    }

    /**
     * Add the settings page to the admin menu.
     */
    public function addMenuPage(): void
    {
        add_submenu_page(
            $this->getParentSlug(),
            $this->getPageTitle(),
            $this->getMenuTitle(),
            $this->getCapability(),
            $this->getMenuSlug(),
            [$this, 'renderPage']
        );
    }

    /**
     * Handle form submissions.
     */
    public function handleFormSubmission(): void
    {
        if (!isset($_POST['licensesos_action'])) {
            return;
        }

        if (!check_admin_referer('licensesos_license_action', 'licensesos_nonce')) {
            return;
        }

        if (!current_user_can($this->getCapability())) {
            return;
        }

        $action = sanitize_text_field($_POST['licensesos_action']);
        $manager = $this->getLicenseManager();

        switch ($action) {
            case 'activate':
                $this->handleActivate($manager);
                break;

            case 'deactivate':
                $this->handleDeactivate($manager);
                break;

            case 'refresh':
                $this->handleRefresh($manager);
                break;
        }
    }

    /**
     * Render the settings page.
     */
    public function renderPage(): void
    {
        $this->handleFormSubmission();
        $manager = $this->getLicenseManager();

        $licenseKey = $manager->getLicenseKey();
        $status = $manager->getStatus();
        $data = $manager->getLicenseData();
        $isPremium = $manager->isPremium();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html($this->getPageTitle()); ?></h1>

            <?php $this->renderNotices(); ?>

            <div class="licensesos-license-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 600px; margin-top: 20px;">
                <?php if ($licenseKey): ?>
                    <?php $this->renderLicenseStatus($licenseKey, $status, $data, $isPremium); ?>
                <?php else: ?>
                    <?php $this->renderActivationForm(); ?>
                <?php endif; ?>
            </div>

            <?php $this->renderAdditionalContent(); ?>
        </div>
        <?php
    }

    /**
     * Render license status view.
     */
    protected function renderLicenseStatus(
        string $licenseKey,
        string $status,
        ?array $data,
        bool $isPremium
    ): void {
        $maskedKey = $this->maskLicenseKey($licenseKey);
        $statusColor = $this->getStatusColor($status);
        $statusLabel = $this->getStatusLabel($status);

        ?>
        <h2 style="margin-top: 0;">License Status</h2>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">License Key</th>
                <td>
                    <code style="font-size: 14px;"><?php echo esc_html($maskedKey); ?></code>
                </td>
            </tr>
            <tr>
                <th scope="row">Status</th>
                <td>
                    <span style="display: inline-block; padding: 4px 12px; border-radius: 4px; background: <?php echo esc_attr($statusColor); ?>; color: #fff; font-weight: 500;">
                        <?php echo esc_html($statusLabel); ?>
                    </span>
                    <?php if ($isPremium): ?>
                        <span style="color: #46b450; margin-left: 10px;">&#10003; Premium features enabled</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($data && !empty($data['expires_at'])): ?>
            <tr>
                <th scope="row">Expires</th>
                <td><?php echo esc_html($this->formatDate($data['expires_at'])); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($data && !empty($data['entitlements'])): ?>
            <tr>
                <th scope="row">Entitlements</th>
                <td>
                    <?php foreach ($data['entitlements'] as $key => $value): ?>
                        <code><?php echo esc_html($key); ?>: <?php echo esc_html(is_bool($value) ? ($value ? 'yes' : 'no') : (string)$value); ?></code><br>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($data && !empty($data['cached_at'])): ?>
            <tr>
                <th scope="row">Last Checked</th>
                <td>
                    <?php echo esc_html($this->formatDate(date('c', $data['cached_at']))); ?>
                    <?php if ($this->getLicenseManager()->needsRefresh()): ?>
                        <em style="color: #666;">(needs refresh)</em>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
        </table>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('licensesos_license_action', 'licensesos_nonce'); ?>
                <input type="hidden" name="licensesos_action" value="refresh">
                <button type="submit" class="button">
                    Refresh Status
                </button>
            </form>

            <form method="post" style="display: inline; margin-left: 10px;">
                <?php wp_nonce_field('licensesos_license_action', 'licensesos_nonce'); ?>
                <input type="hidden" name="licensesos_action" value="deactivate">
                <button type="submit" class="button" onclick="return confirm('Are you sure you want to deactivate this license?');">
                    Deactivate License
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * Render activation form.
     */
    protected function renderActivationForm(): void
    {
        ?>
        <h2 style="margin-top: 0;">Activate License</h2>
        <p>Enter your license key to activate premium features.</p>

        <form method="post">
            <?php wp_nonce_field('licensesos_license_action', 'licensesos_nonce'); ?>
            <input type="hidden" name="licensesos_action" value="activate">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="license_key">License Key</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="license_key"
                            id="license_key"
                            class="regular-text"
                            placeholder="LIC-XXXX-XXXX-XXXX-XXXX"
                            required
                            pattern="LIC-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}"
                            style="font-family: monospace;"
                        >
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    Activate License
                </button>
            </p>
        </form>

        <p class="description">
            Don't have a license? <a href="<?php echo esc_url($this->getPurchaseUrl()); ?>" target="_blank">Purchase one here</a>.
        </p>
        <?php
    }

    /**
     * Render admin notices.
     */
    protected function renderNotices(): void
    {
        $notices = get_transient($this->getMenuSlug() . '_notices');
        if (!$notices) {
            return;
        }

        delete_transient($this->getMenuSlug() . '_notices');

        foreach ($notices as $notice) {
            $type = $notice['type'] ?? 'info';
            $message = $notice['message'] ?? '';
            ?>
            <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
                <p><?php echo esc_html($message); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Render additional content after the license card.
     * Override to add custom content.
     */
    protected function renderAdditionalContent(): void
    {
        // Override in subclass if needed
    }

    /**
     * Get the purchase URL.
     * Override to return your purchase page URL.
     */
    protected function getPurchaseUrl(): string
    {
        return '#';
    }

    /**
     * Handle license activation.
     */
    protected function handleActivate(LicenseManager $manager): void
    {
        $licenseKey = sanitize_text_field($_POST['license_key'] ?? '');

        if (empty($licenseKey)) {
            $this->addNotice('error', 'Please enter a license key.');
            return;
        }

        try {
            $result = $manager->activate($licenseKey);

            if ($result->isActivated()) {
                $this->addNotice('success', 'License activated successfully!');
            } else {
                $errorMessage = $result->getErrorMessage() ?? 'Activation failed.';
                $this->addNotice('error', $errorMessage);
            }
        } catch (\Exception $e) {
            $this->addNotice('error', 'Activation failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle license deactivation.
     */
    protected function handleDeactivate(LicenseManager $manager): void
    {
        if ($manager->deactivate()) {
            $this->addNotice('success', 'License deactivated successfully.');
        } else {
            $this->addNotice('error', 'Failed to deactivate license.');
        }
    }

    /**
     * Handle license refresh.
     */
    protected function handleRefresh(LicenseManager $manager): void
    {
        $result = $manager->validate(true);

        if ($result !== null) {
            $this->addNotice('success', 'License status refreshed.');
        } else {
            $this->addNotice('warning', 'Could not refresh license status. Using cached data.');
        }
    }

    /**
     * Add a notice to display.
     */
    protected function addNotice(string $type, string $message): void
    {
        $notices = get_transient($this->getMenuSlug() . '_notices') ?: [];
        $notices[] = ['type' => $type, 'message' => $message];
        set_transient($this->getMenuSlug() . '_notices', $notices, 60);
    }

    /**
     * Mask a license key for display.
     */
    protected function maskLicenseKey(string $licenseKey): string
    {
        // Show first segment and last 4 characters
        if (strlen($licenseKey) > 12) {
            return substr($licenseKey, 0, 8) . '••••-••••-' . substr($licenseKey, -4);
        }
        return $licenseKey;
    }

    /**
     * Get status color.
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'active' => '#46b450',
            'expired' => '#dc3232',
            'revoked' => '#dc3232',
            default => '#888',
        };
    }

    /**
     * Get status label.
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'expired' => 'Expired',
            'revoked' => 'Revoked',
            default => 'Unknown',
        };
    }

    /**
     * Format a date for display.
     */
    protected function formatDate(string $date): string
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }
}
