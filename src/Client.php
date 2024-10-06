<?php

declare(strict_types = 1);

namespace Nylas;

use function ucfirst;
use function class_exists;

use Nylas\Utilities\Options;
use Nylas\Exceptions\NylasException;

/**
 * ----------------------------------------------------------------------------------
 * Nylas Client
 * ----------------------------------------------------------------------------------
 *
 * @property Utilities\Options  Options
 * @property Authentication\Abs Authentication
 * @property Calendars\Abs      Calendars
 * @property Contacts\Abs       Contacts
 * @property Deltas\Abs         Deltas
 * @property Drafts\Abs         Drafts
 * @property Events\Abs         Events
 * @property Files\Abs          Files
 * @property Folders\Abs        Folders
 * @property JobStatuses\Abs    JobStatuses
 * @property Labels\Abs         Labels
 * @property Management\Abs     Management
 * @property Messages\Abs       Messages
 * @property Neural\Abs         Neural
 * @property Outbox\Abs         Outbox
 * @property Rooms\Abs          Rooms
 * @property Schedulers\Abs     Schedulers
 * @property Threads\Abs        Threads
 * @property Webhooks\Abs       Webhooks
 *
 * @author lanlin
 * @change 2023/07/21
 */
class Client
{
    // ------------------------------------------------------------------------------

    /**
     * @var array
     */
    private array $objects = [];

    // ------------------------------------------------------------------------------

    /**
     * Client constructor.
     *
     * @param array $options
     *                       [
     *                       'debug'            => bool,
     *                       'region'           => 'us',
     *                       'log_file'         => 'log file path',
     *                       'api_token'        => 'required',
     *                       'access_token'     => '',
     *                       ]
     */
    public function __construct(array $options)
    {
        $this->objects['Options'] = new Options($options);
    }

    // ------------------------------------------------------------------------------

    /**
     * call nylas apis with __get
     *
     * @param string $name
     *
     * @return object
     */
    public function __get(string $name): object
    {
        return $this->callSubClass($name);
    }

    // ------------------------------------------------------------------------------

    /**
     * call sub class
     *
     * @param string $name
     *
     * @return object
     */
    private function callSubClass(string $name): object
    {
        $name = ucfirst($name);

        if (!empty($this->objects[$name]))
        {
            return $this->objects[$name];
        }

        $apiClass = __NAMESPACE__.'\\'.$name.'\\Abs';

        // check class exists
        if (!class_exists($apiClass))
        {
            throw new NylasException(null, "class {$apiClass} not found!");
        }

        return $this->objects[$name] = new $apiClass($this->objects['Options']);
    }

    // ------------------------------------------------------------------------------
}
