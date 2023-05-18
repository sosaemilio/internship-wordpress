<?php

namespace StellarWP\PluginFramework\Console\Commands;

use StellarWP\PluginFramework\Console\Concerns\HasContextAwareness;
use StellarWP\PluginFramework\Console\Contracts\IsContextAware;
use StellarWP\PluginFramework\Console\WPCommand;
use StellarWP\PluginFramework\Contracts\ProvidesSupportUsers;
use StellarWP\PluginFramework\Modules\SupportUsers;

/**
 * Commands for managing support users.
 */
class SupportUserCommand extends WPCommand implements IsContextAware
{
    use HasContextAwareness;

    /**
     * An implementation of the ProvidesSupportUsers interface.
     *
     * @var ProvidesSupportUsers
     */
    protected $service;

    /**
     * Construct a new instance of the command.
     *
     * @param ProvidesSupportUsers $service An implementation of the ProvidesSupportUsers interface.
     */
    public function __construct(ProvidesSupportUsers $service)
    {
        $this->service = $service;
    }

    /**
     * Retrieve a list of all support users on this site.
     *
     * @return void
     */
    public function all()
    {
        $now = time();

        /** @var Array<Array<string,scalar>> $rows */
        $rows = array_map(function ($user) use ($now) {
            $expiration = get_user_meta($user->ID, SupportUsers::USER_META_KEY, true);

            if (! is_scalar($expiration)) {
                $expires_at = '?';
                $this->warning(sprintf(
                    'Support user %d has an invalid expiration time, further investigation is recommended.',
                    $user->ID
                ));
            } else {
                $expiration = (int) $expiration;
                $expires_at = $expiration <= $now
                    ? sprintf('%s ago', human_time_diff($expiration, $now))
                    : human_time_diff($expiration, $now);
            }

            return [
                'ID'         => $user->ID,
                'Email'      => $user->user_email,
                'Expiration' => $expires_at,
            ];
        }, $this->service->all());

        if (empty($rows)) {
            $this->warning('There are no support users present on this site');
        }

        $this->table($rows, ['ID', 'Email', 'Expiration']);
    }

    /**
     * Create a new support user.
     *
     * @return void
     */
    public function create()
    {
        $password = wp_generate_password();
        $user     = $this->service->create([
            'user_pass' => $password,
        ]);

        $this->success('A new support user has been created!')
            ->line()
            ->line($this->colorize("\t%Wurl:%N ") . wp_login_url())
            ->line($this->colorize("\t%Wusername:%N {$user->user_login}"))
            ->line($this->colorize("\t%Wpassword:%N ") . $password)
            ->line()
            ->line('This user will automatically expire in 24 hours. You may also remove it manually by running:')
            ->line()
            ->line($this->colorize("\t%c$ wp {$this->getCommandNamespace()} delete {$user->ID}%n"));
    }

    /**
     * Delete one or more existing support users.
     *
     * ## OPTIONS
     *
     * [<id>...]
     * : One or more support user IDs to create.
     *
     * [--all]
     * : Remove all support users.
     *
     * [--expired]
     * : Only delete support users that have reached their expiration date.
     *
     * ## EXAMPLES
     *
     * # Delete support users with IDs 67 and 68
     * $ wp stellarwp support-user delete 67 68
     *
     * # Delete all expired support users
     * $ wp stellarwp support-user delete --expired
     *
     * # Remove all support users, regardless of expiration status
     * $ wp stellarwp support-user delete --all
     *
     * @param Array<int,scalar>     $args Positional arguments.
     * @param Array<string,?scalar> $opts Options passed to the command.
     *
     * @return void
     */
    public function delete($args, $opts)
    {
        $users = $this->service->all();
        $count = 0;

        $opts  = wp_parse_args($opts, [
            'all'     => false,
            'expired' => false,
        ]);

        // Unless --all is present, filter the list of users.
        if (! $opts['all']) {
            // If --expired is present, append the IDs of any expired support users to $args.
            if ($opts['expired']) {
                $args = array_merge($args, wp_list_pluck($this->service->expired(), 'ID'));
            }

            // For cleaner comparisons, ensure IDs are all treated as integers.
            $args = array_unique(array_map('intval', $args));

            // Unless --all was passed, filter the list of support users to the given IDs.
            $users = array_filter($users, function ($user) use ($args) {
                return in_array((int) $user->ID, $args, true);
            });
        }

        foreach ($users as $user) {
            wp_delete_user($user->ID);
            $this->log(sprintf('Removed user %s (ID %d)', $user->user_login, $user->ID));
            $count++;
        }

        if (! $count) {
            $this->warning('No support users were deleted');
        } else {
            $this->success('%d support user(s) were deleted successfully!');
        }
    }
}
