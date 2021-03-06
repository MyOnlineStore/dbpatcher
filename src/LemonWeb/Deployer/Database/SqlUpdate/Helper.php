<?php

namespace LemonWeb\Deployer\Database\SqlUpdate;

use LemonWeb\Deployer\Exceptions\DeployException;


class Helper
{
    const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Extracts the timestamp out of the filename of a patch
     *
     * @param string $filename
     * @throws \InvalidArgumentException
     * @return string
     */
    public static function convertFilenameToDateTime($filename)
    {
        if (preg_match('/sql_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/', $filename, $matches)) {
            $timestamp = $matches[1] . $matches[2] . $matches[3] . $matches[4] . $matches[5] . $matches[6];

            return $timestamp;
        }

        throw new \InvalidArgumentException("Can't convert $filename to timestamp");
    }

    /**
     * Check if all files exist and contain the right class and correct sql code, and are active.
     *
     * @param string $path_prefix
     * @param array $filepaths
     * @param array $patch_options      Options array that will be passed on to the patches' constructor
     * @throws \LemonWeb\Deployer\Exceptions\DeployException
     * @throws \LemonWeb\Deployer\Database\SqlUpdate\InvalidPatchException
     * @return SqlUpdateInterface[]                [filepath => sql_update_object, ...]
     */
    public static function checkFiles($path_prefix, $filepaths, array $patch_options = null)
    {
        if (null == $patch_options) {
            $patch_options = array();
        }

        $sql_patch_objects = array();

        foreach ($filepaths as $filename) {
            $filepath = $path_prefix .'/'. $filename;

            if (!file_exists($filepath)) {
                throw new DeployException("$filepath not found");
            }

            $classname = self::getClassnameFromFilepath($filepath);

            if (!class_exists($classname)) {
                require $filepath;
            }

            if (!class_exists($classname)) {
                throw new InvalidPatchException("Class $classname not found in $filepath");
            }

            $sql_patch = new $classname($patch_options);

            if (!$sql_patch instanceof SqlUpdateInterface) {
                throw new InvalidPatchException("Class $classname doesn't implement the SQL_update interface");
            }

            $up_sql = trim($sql_patch->up());

            if ('' != $up_sql && substr($up_sql, -1) != ';') {
                throw new InvalidPatchException("$classname up() method contains code but doesn't end with ';'");
            }

            $down_sql = trim($sql_patch->down());

            if ('' != $down_sql && substr($down_sql, -1) != ';') {
                throw new InvalidPatchException("$classname down() method contains code but doesn't end with ';'");
            }

            if ($sql_patch->isActive()) {
                $sql_patch_objects[$filename] = $sql_patch;
            }
        }

        return $sql_patch_objects;
    }

    /**
     * @param string $filepath
     * @return string
     */
    public static function getClassnameFromFilepath($filepath)
    {
        return pathinfo($filepath, PATHINFO_FILENAME);
    }
}
