<?php

/**
 * APIFactory module.
 *
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2018 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 *
 * @link      https://docs.madelineproto.xyz MadelineProto documentation
 */

namespace danog\MadelineProto;

use Amp\Promise;

class APIFactory
{
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var langpack
     */
    public $langpack;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var phone
     */
    public $phone;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var stickers
     */
    public $stickers;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var payments
     */
    public $payments;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var bots
     */
    public $bots;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var channels
     */
    public $channels;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var help
     */
    public $help;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var upload
     */
    public $upload;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var photos
     */
    public $photos;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var updates
     */
    public $updates;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var messages
     */
    public $messages;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var contacts
     */
    public $contacts;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var users
     */
    public $users;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var account
     */
    public $account;
    /**
     * @internal this is a internal property generated by build_docs.php, don't change manually
     *
     * @var auth
     */
    public $auth;

    use Tools;
    public $namespace = '';
    public $API;
    public $lua = false;
    public $async = false;

    protected $methods = [];

    public function __construct($namespace, $API)
    {
        $this->namespace = $namespace.'.';
        $this->API = $API;
    }

    public function __call($name, $arguments)
    {
        if (Magic::is_fork() && !Magic::$processed_fork) {
            \danog\MadelineProto\Logger::log('Detected fork');
            $this->API->reset_session();
            foreach ($this->API->datacenter->sockets as $id => $datacenter) {
                $this->API->close_and_reopen($id);
            }
            Magic::$processed_fork = true;
        }

        if ($this->API->setdem) {
            $this->API->setdem = false;
            $this->API->__construct($this->API->settings);
        }
        $this->API->get_config([], ['datacenter' => $this->API->datacenter->curdc]);

        if (isset($this->session) && !is_null($this->session) && time() - $this->serialized > $this->API->settings['serialization']['serialization_interval']) {
            Logger::log("Didn't serialize in a while, doing that now...");
            $this->serialize($this->session);
        }
        if ($name !== 'accept_tos' && $name !== 'decline_tos') {
            $this->API->check_tos();
        }
        $lower_name = strtolower($name);

        if ($this->lua === false) {
            return $this->namespace !== '' || !isset($this->methods[$lower_name]) ? $this->__mtproto_call($this->namespace.$name, $arguments) : $this->__api_call($lower_name, $arguments);
        }

        try {
            $deserialized = $this->namespace !== '' || !isset($this->methods[$lower_name]) ? $this->__mtproto_call($this->namespace.$name, $arguments) : $this->__api_call($lower_name, $arguments);

            Lua::convert_objects($deserialized);

            return $deserialized;
        } catch (\danog\MadelineProto\Exception $e) {
            return ['error_code' => $e->getCode(), 'error' => $e->getMessage()];
        } catch (\danog\MadelineProto\RPCErrorException $e) {
            return ['error_code' => $e->getCode(), 'error' => $e->getMessage()];
        } catch (\danog\MadelineProto\TL\Exception $e) {
            return ['error_code' => $e->getCode(), 'error' => $e->getMessage()];
        } catch (\danog\MadelineProto\NothingInTheSocketException $e) {
            return ['error_code' => $e->getCode(), 'error' => $e->getMessage()];
        } catch (\danog\MadelineProto\PTSException $e) {
            return ['error_code' => $e->getCode(), 'error' => $e->getMessage()];
        } catch (\danog\MadelineProto\SecurityException $e) {
            return ['error_code' => $e->getCode(), 'error' => $e->getMessage()];
        } catch (\danog\MadelineProto\TL\Conversion\Exception $e) {
            return ['error_code' => $e->getCode(), 'error' => $e->getMessage()];
        }
    }

    public function __api_call($name, $arguments)
    {
        $result = $this->methods[$name](...$arguments);
        if (is_object($result) && ($result instanceof \Generator || $result instanceof Promise)) {
            $async = is_array(end($arguments)) && isset(end($arguments)['async']) ? end($arguments)['async'] : $this->async;
            if ($async && ($name !== 'loop' || isset(end($arguments)['async']))) {
                return $result;
            } else {
                return $this->wait($result);
            }
        }

        return $result;
    }

    public function __mtproto_call($name, $arguments)
    {
        $aargs = isset($arguments[1]) && is_array($arguments[1]) ? $arguments[1] : [];
        $aargs['datacenter'] = $this->API->datacenter->curdc;
        $aargs['apifactory'] = true;
        $args = isset($arguments[0]) && is_array($arguments[0]) ? $arguments[0] : [];

        $async = isset(end($arguments)['async']) ? end($arguments)['async'] : $this->async;
        $res = $this->API->method_call_async_read($name, $args, $aargs);

        if ($async) {
            return $res;
        } else {
            return $this->wait($res);
        }
    }
}
