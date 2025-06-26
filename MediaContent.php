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


    public function rename(int $iBlockId): void
    {
        if (!\Bitrix\Main\Loader::includeModule('av.rest')) {
            throw new AvRestRuntimeException('Не удалось подключить модуль av.rest');
        }

        $countRenamed = self::BEGIN_COUNT;

        $arFilter = ['IBLOCK_ID' => $iBlockId, 'GLOBAL_ACTIVE' => 'Y'];

        $result = \CIBlockElement::GetList(['SORT' => 'ASC'], $arFilter);

        if (!$result) {
            throw new GetMediaFileException('Ошибка получения списка элементов GetList');
        }

        while ($file = $result->GetNext()) {
            $previewId = isset($file['PREVIEW_PICTURE']) ? (int) $file['PREVIEW_PICTURE'] : 0;
            $detailId = isset($file['DETAIL_PICTURE']) ? (int) $file['DETAIL_PICTURE'] : 0;

            if ($previewId === 0 && $detailId === 0) {
                printf("Element with ID %s skipped - images not found.\n", $file['ID']);
                continue;
            }

            try {
                if ($previewId > 0) {
                    $this->modifyFileName($previewId);
                }

                if ($detailId > 0) {
                    $this->modifyFileName($detailId);
                }

                $countRenamed += self::ONE_UP;

                printf(
                    "File for element with ID %s named as \"%s\" successfully renamed\n",
                            $file['ID'],
                            $file['NAME']
                );
            } catch (\Exception $exception) {
                printf(
                    "Unable to rename file(s) with ID %s: %s\n",
                            $file['ID'],
                            $exception->getMessage()
                );
                continue;
            }
        }

        printf("Success, totally files renamed: %d \n", $countRenamed);
    }

    private function modifyFileName(int $id): void
    {
        $realFilePath = $this->getRealFilePath($id);
        $realFileName = $this->getRealFileName($id);

        if (!$realFilePath || !$realFileName) {
            throw new FileOrPathNotFoundException("Unable to get path or file name for ID $id");
        }

        $newFileName = $this->getMD5FileName($realFileName)
            . '.' . pathinfo($realFilePath
            . '/' . $realFileName, PATHINFO_EXTENSION);

        $oldFilePath = Application::getDocumentRoot() . $realFilePath . '/' . $realFileName;
        $modifiedFilePath = Application::getDocumentRoot() . $realFilePath . '/' . $newFileName;

        $file = new File($oldFilePath);
        $file->rename($modifiedFilePath);

        $this->modifyDBTableFileName($id, $newFileName);
    }

    private function getRealFilePath(int $id): string
    {
        $file = \CFile::GetFileArray($id);

        return '/upload/' . $file['SUBDIR'];
    }

    private function getRealFileName(int $id): string
    {
        $file = \CFile::GetFileArray($id);

        return $file['FILE_NAME'];
    }

    private function modifyDBTableFileName(int $id, string $fileName): void
    {
        $connection = Application::getConnection();

        $connection->queryExecute(
            "UPDATE b_file SET FILE_NAME = ?, ORIGINAL_NAME = ? WHERE ID = ?",
            [$fileName, $fileName, $id]
        );
    }

    private function getMD5FileName(string $fileName): string
    {
        return hash('md5', $fileName);
    }
}