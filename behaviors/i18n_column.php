<?php
/**
 * I18nColumn behavior
 */
class I18nColumnBehavior extends ModelBehavior {

    const DEFAULT_LANGUAGE = 'ja';

    private $config = array(
        'language'      => array(), //使用言語
        'i18n_fields'   => array(), //国際化対応する仮想カラム名
        'i18n_column'   => 'i18n',  //国際化情報を保存するDBカラム名
    );

    /**
     * 言語リスト
     */
    function languageList(){
        return $this->config['language'];
    }

    /**
     * カラムリスト
     * @return mixed
     */
    function i18nFieldList(){
        return $this->config['i18n_fields'];
    }

    /**
     * setup
     * @param object $model
     * @param array $config
     * @return bool|void
     */
    function setup(&$model, $config = array()) {

        $this->config = array_merge($this->config, $config);
    }

    function cleanup(&$model) {

    }

    /**
     * beforeFind
     * @param object $model
     * @param $query
     * @return mixed
     */
    function beforeFind(&$model, $query) {

        if(
            $model->hasField($this->config['i18n_column'])
            && !isset($query[$model->name]['fields'][$this->config['i18n_column']])
        ){
            //国際化カラムをfindする
            $query[$model->name]['fields'][]= $this->config['i18n_column']; //TODO group時は無視
        }

        return $query;
    }

    /**
     * afterFind
     * @param object $model
     * @param mixed $results
     * @param bool $primary
     * @return mixed
     */
    function afterFind(&$model, $results, $primary) {

        foreach($results as $key=>&$val){

            if(!isset($val[$model->name])) continue;
            $i18n_results = array();    //結果セットに追加する内容
            $i18n_data = array();       //serialize済み多言語カラムデータ

            if(isset($val[$model->name][$this->config['i18n_column']])){
                $i18n_data = unserialize($val[$model->name][$this->config['i18n_column']]);
            }

            foreach($this->config['language'] as $language){

                if($language == self::DEFAULT_LANGUAGE) continue; //ベース言語は無視

                if(array_key_exists($language, $i18n_data)){

                    $i18n_row = $i18n_data[$language];
                    foreach($this->config['i18n_fields'] as $val2){
                        $i18n_results[$val2 . '_' . $language] = isset($i18n_row[$val2]) ? $i18n_row[$val2] : null;
                    }

                }else {

                    foreach($this->config['i18n_fields'] as $val3){
                        $i18n_results[$val3 . '_' . $language] = null;
                    }
                }
            }

            $val[$model->name] = array_merge($val[$model->name], $i18n_results);
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
        return true;
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
        //TODO 言語によるバリデーションの違いのテスト
        foreach($this->config['language'] as $language){

            if($language == self::DEFAULT_LANGUAGE) continue; //ベース言語は無視

            foreach($this->config['i18n_fields'] as $fields){
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

        foreach($this->config['language'] as $language){

            if($language == self::DEFAULT_LANGUAGE) continue; //ベース言語は無視

            foreach($this->config['i18n_fields'] as $fields){
                if(isset($model->data[$model->name][$fields . '_' . $language])){
                    $i18n_data[$language][$fields] = $model->data[$model->name][$fields . '_' . $language];
                }
            }
        }

        $model->data[$model->name][$this->config['i18n_column']] = !empty($i18n_data) ? serialize($i18n_data) : array();
    }
}
