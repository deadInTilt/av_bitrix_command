<?php

namespace AvCorp\Command;

// use AvCorp\Application as AvCorpApplication;
use Exception;
use \Bitrix\Main\IO\File;
use \Bitrix\Main\Application;
use Monolog\Registry;

class MediaContent
{
    private const BEGIN_COUNT = 0;
    private const ONE_UP = 1;

    private $mediaFiles = [];
    private $newFileName = '';

    public function __construct(int $iBlockId)
    {
        \Bitrix\Main\Loader::includeModule('av.rest');

        $arFilter = ['IBLOCK_ID' => $iBlockId, 'GLOBAL_ACTIVE' => 'Y'];

        $result = \CIBlockElement::GetList(['SORT' => 'ASC'], $arFilter);

        while($element = $result->GetNext())
        {
            $this->mediaFiles[] = $element;
        }
    }

    public function rename(): void
    {
        $countRenamed = self::BEGIN_COUNT;

        foreach($this->mediaFiles as $file) {
            try {
                $this->modifyFileName((int) $file['PREVIEW_PICTURE']);
                $this->modifyFileName((int) $file['DETAIL_PICTURE']);

                $this->modifyDBTableFileName((int) $file['PREVIEW_PICTURE'], $this->newFileName);
                $this->modifyDBTableFileName((int) $file['DETAIL_PICTURE'], $this->newFileName);

                $countRenamed += self::ONE_UP;

                printf("File for element with id %s named as %s successfully renamed\n", $file['ID'], $file['NAME']);
            } catch (Exception $exception) {
                printf("Unable to rename file with id %s \n", $file['ID']);

                continue;
            }
        }

        printf("Success, totally files renamed: %d \n", $countRenamed);
    }

    private function modifyFileName(int $id = null): void
    {
        if (empty($id)) {
            return;
        }

        $realFilePath = $this->getRealFilePath($id);
        $realFileName = $this->getRealFileName($id);

        $this->newFileName = $this->getMD5FileName($realFileName)
            . '.' . pathinfo($realFilePath
            . '/' . $realFileName, PATHINFO_EXTENSION);

        $oldFilePath = Application::getDocumentRoot() . $realFilePath . '/' . $realFileName;
        $modifiedFilePath = Application::getDocumentRoot() . $realFilePath . '/' . $this->newFileName;

        $file = new File($oldFilePath);
        $file->rename($modifiedFilePath);
    }

    private function getRealFilePath(int $id = null): string
    {
        if (empty($id)) {
            return '';
        }

        $file = \CFile::GetFileArray($id);

        return '/upload/' . $file['SUBDIR'];
    }

    private function getRealFileName(int $id = null): string
    {
        if (empty($id)) {
            return '';
        }

        $file = \CFile::GetFileArray($id);

        return $file['FILE_NAME'];
    }

    private function modifyDBTableFileName(int $id, string $fileName): void
    {
        $connection = Application::getConnection();

        $connection->queryExecute(
            "UPDATE b_file
                SET FILE_NAME = '$fileName', ORIGINAL_NAME = '$fileName'
                WHERE ID = $id "
        );
    }

    private function getMD5FileName(string $fileName = null): string
    {
        if (empty($fileName)) {
            return '';
        }

        return hash('md5', $fileName);
    }
}