<?php

namespace Pterodactyl\Services\Store;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Pterodactyl\Http\Requests\Api\Client\Store\CreateServerRequest;

class StoreVerificationService
{
    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * This service ensures that users cannot create servers, gift
     * resources or edit a servers resource limits if they do not
     * have sufficient resources in their account - or if the requested
     * amount goes over admin-defined limits.
     */
    public function handle(CreateServerRequest $request)
    {
        $this->checkUserResources($request);
        $this->checkResourceLimits($request);
    }

    private function checkUserResources(CreateServerRequest $request)
    {
        $types = ['cpu', 'memory', 'disk', 'slots', 'ports', 'backups', 'databases'];

        foreach ($types as $type) {
            $value = DB::table('users')->where('id', $request->user()->id)->value('store_' . $type);

            if ($value < $request->input($type)) {
                throw new DisplayException('You only have' . $value . ' ' . $type . ', so you cannot deploy this server.');
            }
        }
    }

    private function checkResourceLimits(CreateServerRequest $request)
    {
        $prefix = 'jexactyl::store:limit:';
        $types = ['cpu', 'memory', 'disk', 'slot', 'port', 'backup', 'database'];

        foreach ($types as $type) {
            $suffix = '';
            $limit = $this->settings->get($prefix . $type);

            if (in_array($type, ['slot', 'port', 'backup', 'database'])) {
                $suffix = 's';
            }

            $amount = $request->input($type .= $suffix);

            if ($limit < $amount) {
                throw new DisplayException('You cannot deploy with ' . $amount . ' ' . $type . ', as an admin has set a limit of ' . $limit);
            }
        }
    }
}
