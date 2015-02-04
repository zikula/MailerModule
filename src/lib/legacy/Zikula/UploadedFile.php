<?php
/**
 * Copyright 2015 Zikula Foundation.
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 * @package Zikula
 * @subpackage Zikula_Exception
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

/**
 * A file uploaded through a form.
 *
 * @deprecated as of 1.4.0
 * @see \Symfony\Component\HttpFoundation\File\UploadedFile
 */
class Zikula_UploadedFile extends \Symfony\Component\HttpFoundation\File\UploadedFile implements \ArrayAccess
{
    /**
     * @deprecated at 1.4.0
     * Whether a offset exists
     * @param mixed $offset
     * An offset to check for.
     *
     * @return boolean true on success or false on failure.
     */
    public function offsetExists($offset)
    {
        $value = $this->offsetGet($offset);

        return isset($value);
    }

    /**
     * @deprecated at 1.4.0
     * Offset to retrieve
     * @param mixed $offset
     * The offset to retrieve.
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        LogUtil::log('Array Access to file properties is deprecated. Please use SPL methods.', E_USER_DEPRECATED);

        switch ($offset) {
            case 'name':
                $value = $this->getClientOriginalName();
                break;
            case 'type':
                $value = $this->getClientMimeType();
                break;
            case 'size':
                $value = $this->getClientSize();
                break;
            case 'tmp_name':
                $value = $this->getRealPath();
                break;
            case 'error':
                $value = $this->getError();
                break;
            default:
                $value = null;
        }

        return $value;
    }

    /**
     * @deprecated at 1.4.0
     * Offset to set
     * @param mixed $offset
     * The offset to assign the value to.
     * @param mixed $value
     * The value to set.
     * @throws \Exception
     */
    public function offsetSet($offset, $value)
    {
        throw new \Exception("It is not possible to set values via Array Access. Please use SPL methods.");
    }

    /**
     * @deprecated at 1.4.0
     * Offset to unset
     * @param mixed $offset
     * The offset to unset.
     * @throws \Exception
     */
    public function offsetUnset($offset)
    {
        throw new \Exception("It is not possible to unset values via Array Access. Please use SPL methods.");
    }
}