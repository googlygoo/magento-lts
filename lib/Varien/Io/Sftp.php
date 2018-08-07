<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magento.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    Varien
 * @package     Varien_Io
 * @copyright  Copyright (c) 2006-2018 Magento, Inc. (http://www.magento.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use \phpseclib\Net\SFTP;

/**
 * Sftp client interface
 *
 * @category   Varien
 * @package    Varien_Io
 * @author     Magento Core Team <core@magentocommerce.com>
 * @link       http://www.php.net/manual/en/function.ssh2-connect.php
 */
class Varien_Io_Sftp extends Varien_Io_Abstract implements Varien_Io_Interface
{
    const REMOTE_TIMEOUT = 10;
    const SSH2_PORT = 22;

    /**
     * @var SFTP $_connection
     */
    protected $_connection = null;


    /**
     * Open a SFTP connection to a remote site.
     *
     * @param array $args Connection arguments
     *                    host     - Remote hostname
     *                    username - Remote username
     *                    password - Connection password
     *                    timeout  - [Optional] Connection timeout
     *
     * @return bool
     * @throws Exception
     */
    public function open(array $args = array())
    {
        if (!isset($args['timeout'])) {
            $args['timeout'] = self::REMOTE_TIMEOUT;
        }
        if (strpos($args['host'], ':') !== false) {
            list($host, $port) = explode(':', $args['host'], 2);
        } else {
            $host = $args['host'];
            $port = self::SSH2_PORT;
        }
        $this->_connection = new SFTP($host, $port, $args['timeout']);
        if (!$this->_connection->login($args['username'], $args['password'])) {
            throw new Exception(sprintf(__("Unable to open SFTP connection as %s@%s", $args['username'], $args['host'])));
        }

        return true;
    }

    /**
     * Close a connection
     *
     */
    public function close()
    {
        $this->_connection->disconnect();
        return ;
    }

    /**
     * Create a directory
     *
     * Note: if $recursive is true and an error occurs mid-execution,
     * false is returned and some part of the hierarchy might be created.
     * No rollback is performed.
     *
     * @param string $dir
     * @param int    $mode      Ignored here; uses logged-in user's umask
     * @param bool   $recursive Analogous to mkdir -p
     *
     * @return bool
     */
    public function mkdir($dir, $mode=0777, $recursive=true)
    {
        if ($recursive) {
            $no_errors = true;
            $dirlist = explode('/', $dir);
            reset($dirlist);
            $cwd = $this->_connection->pwd();
            while ($no_errors && ($dir_item = next($dirlist))) {
                $no_errors = ($this->_connection->mkdir($dir_item) && $this->_connection->chdir($dir_item));
            }
            $this->_connection->chdir($cwd);
            return $no_errors;
        } else {
            return $this->_connection->mkdir($dir);
        }
    }

    /**
     * Delete a directory
     *
     * @param string $dir
     * @param bool   $recursive
     *
     * @return bool
     * @throws Exception
     */
    public function rmdir($dir, $recursive=false)
    {
        if ($recursive) {
            $no_errors = true;
            $cwd = $this->_connection->pwd();
            if(!$this->_connection->chdir($dir)) {
                throw new Exception("chdir(): $dir: Not a directory");
            }
            $list = $this->_connection->nlist();
            if (!count($list)) {
                // Go back
                $this->_connection->chdir($cwd);
                return $this->rmdir($dir, false);
            } else {
                foreach ($list as $filename) {
                    if($this->_connection->chdir($filename)) { // This is a directory
                        $this->_connection->chdir('..');
                        $no_errors = $no_errors && $this->rmdir($filename, $recursive);
                    } else {
                        $no_errors = $no_errors && $this->rm($filename);
                    }
                }
            }
            $no_errors = $no_errors && ($this->_connection->chdir($cwd) && $this->_connection->rmdir($dir));
            return $no_errors;
        } else {
            return $this->_connection->rmdir($dir);
        }
    }

    /**
     * Get current working directory
     *
     */
    public function pwd()
    {
        return $this->_connection->pwd();
    }

    /**
     * Change current working directory
     *
     * @param string $dir
     *
     * @return bool
     */
    public function cd($dir)
    {
        return $this->_connection->chdir($dir);
    }

    /**
     * Read a file
     *
     * @param string                    $filename
     * @param null|string|bool|resource $dest
     *
     * @return mixed
     */
    public function read($filename, $dest=null)
    {
        if (is_null($dest)) {
            $dest = false;
        }
        return $this->_connection->get($filename, $dest);
    }

    /**
     * Write a file
     * @param $src
     */
    /**
     * @param string          $filename
     * @param string|resource $src      Data or file resource
     * @param null            $mode     Ignored, PHP 7.2 method signature compatibility
     *
     * @return bool
     */
    public function write($filename, $src, $mode=null)
    {
        return $this->_connection->put($filename, $src);
    }

    /**
     * Delete a file
     *
     * @param string $filename
     *
     * @return bool
     */
    public function rm($filename)
    {
        return $this->_connection->delete($filename);
    }

    /**
     * Rename or move a directory or a file
     *
     * @param string $src
     * @param string $dest
     *
     * @return bool
     */
    public function mv($src, $dest)
    {
        return $this->_connection->rename($src, $dest);
    }

    /**
     * Chamge mode of a directory or a file
     *
     * @param string $filename
     * @param int    $mode
     *
     * @return mixed
     */
    public function chmod($filename, $mode)
    {
        return $this->_connection->chmod($mode, $filename);
    }

    /**
     * Get list of cwd subdirectories and files
     *
     * @param null $grep Ignored, PHP 7.2 method signature compatibility
     *
     * @return array
     */
    public function ls($grep=null)
    {
        $list = $this->_connection->nlist();
        $pwd = $this->pwd();
        $result = array();
        foreach($list as $name) {
            $result[] = array(
                'text' => $name,
                'id' => "{$pwd}{$name}",
            );
        }
        return $result;
    }

    public function rawls()
    {
        $list = $this->_connection->rawlist();
        return $list;
    }

}
