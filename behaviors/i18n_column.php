<?php
/**
 * I18n behavior
 */
class I18nBehavior extends ModelBehavior {

    const DEFAULT_LANGUAGE = 'ja';
    const LANGUAGE_EN = 'en';
    const LANGUAGE_TW = 'tw';

    public $i18n_find = true; //trueの時、findで国際化カラムを取得する
    public $i18n_column = 'i18n'; //国際化情報を保存するDBカラム名
    public $language = array();

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
     * 言語リスト
     */
    function languageList(){
        return $this->language;
    }

    function setAllLanguage(){
        $this->language = $this->getAllLanguage();
    }

    function getAllLanguage(){
        return array(
            self::LANGUAGE_EN,
            self::LANGUAGE_TW,
        );
    }

    function notReplaceColumn(&$model){
        $this->settings[$model->name]['replace_column'] = false;
    }

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
     */
    function afterFind(&$model, $results, $primary) {

        if($this->i18n_find){
            if($this->settings[$model->name]['replace_column']){
                foreach($results as $key=>&$val){

                    if(!isset($val[$model->name])) continue;
                    $i18n_results = array();    //結果セットに追加する内容
                    $i18n_data = array();       //serialize済み多言語カラムデータ

                    if(isset($val[$model->name][$this->i18n_column])){
                        $i18n_data = unserialize($val[$model->name][$this->i18n_column]);
                    }

                    foreach($this->language as $language){

                        if($language == self::DEFAULT_LANGUAGE) continue; //ベース言語は無視

                        if(array_key_exists($language, $i18n_data)){

                            $i18n_row = $i18n_data[$language];

                            foreach($this->settings[$model->name]['fields'] as $val2){
                                if(isset($i18n_row[$val2]) && !empty($i18n_row[$val2])){
                                    $i18n_results[$val2] = $i18n_row[$val2];
                                }
                            }

                        }
                    }

                    $val[$model->name] = array_merge($val[$model->name], $i18n_results);
                }
            } else {

                foreach($results as $key=>&$val){

                    if(!isset($val[$model->name])) continue;
                    $i18n_results = array();    //結果セットに追加する内容
                    $i18n_data = array();       //serialize済み多言語カラムデータ

                    if(isset($val[$model->name][$this->i18n_column])){
                        $i18n_data = unserialize($val[$model->name][$this->i18n_column]);
                    }

                    foreach($this->language as $language){

                        if($language == self::DEFAULT_LANGUAGE) continue; //ベース言語は無視

                        if(array_key_exists($language, $i18n_data)){

                            $i18n_row = $i18n_data[$language];
                            foreach($this->settings[$model->name]['fields'] as $val2){
                                $i18n_results[$val2 . '_' . $language] = isset($i18n_row[$val2]) ? $i18n_row[$val2] : null;
                            }

                        }else {

                            foreach($this->settings[$model->name]['fields'] as $val3){
                                $i18n_results[$val3 . '_' . $language] = null;
                            }
                        }
                    }

                    $val[$model->name] = array_merge($val[$model->name], $i18n_results);
                }
            }
        }

        return $results;
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
}
