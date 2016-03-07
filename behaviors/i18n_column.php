<?php
/**
 * I18n behavior
 */
class I18nBehavior extends ModelBehavior {

    //言語用
    const DEFAULT_LANGUAGE = 'ja';
    const LANGUAGE_EN = 'en';
    const LANGUAGE_TW = 'tw';

    //設定値
    public $i18n_find = true; //trueの時、findで国際化カラムを取得する
    public $i18n_column = 'i18n'; //国際化情報を保存するDBカラム名
    public $language = array(); //言語

    /*
      i18nカラムのデータ格納形式
        'i18n' => array(
            'language1' => array(
                'field_name1' => 'val1',
                'field_name2' => 'val2',
            ),
            'language2' => array(
                'field_name1' => 'val1',
                'field_name2' => 'val2',
            ),
        )
    */

    /**
     * 使用言語リスト
     */
    function languageList(){
        return $this->language;
    }

    /**
     * 定義済みの全言語を使用する
     */
    function setAllLanguage(){
        $this->language = $this->getAllLanguage();
    }

    /**
     * 定義済み言語リスト
     * @return array
     */
    function getAllLanguage(){
        return array(
            self::LANGUAGE_EN,
            self::LANGUAGE_TW,
        );
    }

    /**
     * カラム置き換えしない
     * @param $model
     */
    function notReplaceColumn(&$model){
        $this->settings[$model->name]['replace_column'] = false;
    }

    /**
     * バックエンド用の指定
     * @param $model
     */
    function useForBackend(&$model){
        $this->setAllLanguage();
        $this->notReplaceColumn($model);
    }

    /**
     * @param $model
     * @return array
     */
    function i18nFieldList(&$model){
        $listFields = array();
        foreach($this->language as $language){
            foreach($this->settings[$model->name]['fields'] as $field){
                $listFields[$language][] =  $field . '_' . $language;
            }
        }
        return $listFields;
    }

    /**
     * setup
     * @param object $model
     * @param array $config
     * @return bool|void
     */
    function setup(&$model, $config = array()) {

        if(isset($this->settings[$model->name])){
            $this->settings[$model->name] = array_merge($this->settings[$model->name], $config);
        } else {
            $this->settings[$model->name] = $config;
        }

        //言語
        if(empty($this->language)){
            $this->language[] = DEFAULT_LANGUAGE;
        }

        //バックエンド用の指定
        if(Configure::read('Public.ApplicationType') == CONST_APPLICATION_BACKEND){
            $this->useForBackend($model);
        }

        //find時のカラム置き換え
        if(!isset($this->settings[$model->name]['replace_column'])){
            $this->settings[$model->name]['replace_column'] = true;
        }
    }



    /**
     * beforeFind
     * @param object $model
     * @param $query
     * @return mixed
     */
    function beforeFind(&$model, $query) {

        if(
            $this->i18n_find
            && $model->hasField($this->i18n_column)
        ){
            //国際化カラムをfindする
            if(
                isset($query['fields'])
                && is_array($query['fields']) //group時は無視
            ){
                $query['fields'][]= $this->i18n_column;
            }
        }

        return parent::beforeFind($model, $query);
    }

    /**
     * afterFind
     * @param object $model
     * @param mixed $results
     * @param bool $primary
     * @return mixed
     * @link http://www.skyarc.co.jp/engineerblog/entry/_cakephpafterfind_phpercakephp_cakephpafterfindphpwarning_cake_3_count_appmodel_afterfind3.html
     */
    function afterFind(&$model, $results, $primary) {

        if(isset($results[0])){

            if(isset($results[0][$model->alias])){
                //array(0 => array(model => array( field => value )))
                foreach($results as $key=>$val){
                    $this->set_split_i18n_value($model, $results[$key][$model->alias]);
                }

            } else {
                //array(0 => array(field => value)))
                foreach($results as $key2=>$val2){
                    $this->set_split_i18n_value($model, $results[$key2]);
                }
            }

        } elseif(is_array($results)) {
            // array(field => value)
            $this->set_split_i18n_value($model, $results);
        }

        return $results;
    }

    /**
     * i18nカラムの更新用データをセット
     * 既存のデータに更新分データを上書き
     */
    function override_i18n_column(&$model){

        if(!empty($model->data[$model->name]['id'])){

            $i18n_field_exists = false;

            foreach($this->getAllLanguage() as $language){ //save時は全言語をチェック
                if($language == self::DEFAULT_LANGUAGE) continue; //ベース言語は無視
                foreach($this->settings[$model->name]['fields'] as $fields){
                    if(isset($model->data[$model->name][$fields . '_' . $language])){
                        $i18n_field_exists = true;
                        break 2;
                    }
                }
            }

            if(!$i18n_field_exists) return;

            $params = array(
                'conditions'    => array('id'=>$model->data[$model->name]['id']),
                'fields'        => array($this->i18n_column),
                'contain'       => false,
            );
            $data = $model->find('first', $params);

            $model->data[$model->name] = $this->array_merge_ex(
                $data[$model->name],
                $model->data[$model->name]
            );

        }
    }

    /**
     * beforeValidate
     * @param object $model
     * @return bool|mixed
     */
    function beforeValidate(&$model) {
        $this->set_validate_i18n_column($model);
    }

    /**
     * beforeSave
     * @param object $model
     * @return mixed|void
     */
    function beforeSave(&$model){
        $this->override_i18n_column($model);
        $this->serialize_i18n_data($model);
    }

    /**
     * 多言語データのバリデーションに元カラムのバリデーションをセット
     * @param $model
     */
    function set_validate_i18n_column(&$model){
        
	foreach($this->getAllLanguage() as $language){ //save時は全言語をチェック

            if($language == self::DEFAULT_LANGUAGE) continue; //ベース言語は無視

            foreach($this->settings[$model->name]['fields'] as $fields){
                if(isset($model->validate[$fields])){
                    $model->validate[$fields . '_' . $language] = $model->validate[$fields];
                    //TODO autoConvert対応
                }
            }
        }
    }

    /**
     * 多言語データをシリアライズ
     * @param $model
     */
    function serialize_i18n_data(&$model){

        $i18n_data = array();

        foreach($this->getAllLanguage() as $language){ //save時は全言語をチェック

            if($language == self::DEFAULT_LANGUAGE) continue; //ベース言語は無視

            foreach($this->settings[$model->name]['fields'] as $fields){

                if(isset($model->data[$model->name][$fields . '_' . $language])){
                    $i18n_data[$language][$fields] = $model->data[$model->name][$fields . '_' . $language];
                }
            }
        }

        $model->data[$model->name][$this->i18n_column] = !empty($i18n_data) ? serialize($i18n_data) : array();
    }

    /**
     * @param $model
     * @param $row
     */
    function set_split_i18n_value(&$model, &$row){
        $i18n_results = array();    //結果セットに追加する内容
        $i18n_data = array();       //serialize済み多言語カラムデータ

        if(isset($row[$this->i18n_column])){

            $i18n_data = unserialize($row[$this->i18n_column]);

            foreach($this->language as $language){
                if($language == self::DEFAULT_LANGUAGE) continue; //ベース言語は無視
                if(array_key_exists($language, $i18n_data)){

                    $i18n_row = $i18n_data[$language];

                    foreach($this->settings[$model->name]['fields'] as $val){
                        if(isset($i18n_row[$val]) && !empty($i18n_row[$val])){
                            if($this->settings[$model->name]['replace_column']){
                                $i18n_results[$val] = $i18n_row[$val];
                            } else {
                                $i18n_results[$val . '_' . $language] = $i18n_row[$val];
                            }
                        }
                    }
                }
            }
            $row = array_merge($row, $i18n_results);
        }
    }

    /**
     * 配列の結合(同一キー有りの場合は$arr2の値で上書き)
     */
    private function array_merge_ex($arr1, $arr2){
        foreach ($arr2 as $key=>$val){
            if(isset($arr1[$key])){
                if (is_array($val)){
                    $arr1[$key] = array_merge_ex($arr1[$key], $val);
                } else {
                    $arr1[$key] = $val;
                }
            } else {
                $arr1[$key] = $val;
            }
        }
        return $arr1;
    }
}
