cakephp-i18n-column
====

DBに多言語向けカラムを追加します

## Install

1. model/behaviors/i18n_column.phpを配置
2. model/app_model.phpを配置
既にapp_modelがある場合はafterFindメソッドを追加
3. 既存テーブルにカラムを追加
```sql
ALTER TABLE `table_name` ADD `i18n` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
```
4. 既存modelにbehaviorを定義
fieldsパラメータには、国際化対応したい既存カラムを指定する
```php
var $actsAs = array(
        'I18n' => array(
            'fields'=>array(
                'column1',
                'column2',
            )
    ));
```

## Usage

save / find
```php
array(
    'TableName' => array(
        'column_1' => '日本語1',
        'column_2' => '日本語2',
        'column_1_en' => 'en1',
        'column_2_en' => 'en2',
    )
)
```

## メソッド
languageList

setAllLanguage

getAllLanguage

notReplaceColumn

useForBackend

i18nFieldList

