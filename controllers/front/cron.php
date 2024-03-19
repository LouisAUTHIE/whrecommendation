<?php

use Rubix\ML\Clusterers\DBSCAN;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Extractors\CSV;
use Rubix\ML\Graph\Trees\BallTree;
use Rubix\ML\Kernels\Distance\Cosine;
use Rubix\ML\Kernels\Distance\Distance;
use Rubix\ML\Transformers\MissingDataImputer;
use Rubix\ML\Transformers\NumericStringConverter;

define('NUMBER_RECOMMENDATION', 5);

class WHRecommendationCronModuleFrontController extends ModuleFrontController
{
    public $auth = false;
    public $ajax;
    private $configuration;

    public function display()
    {
        $this->ajax = 1;
        $this->configuration = $this->get("prestashop.adapter.legacy.configuration");
        $token = $this->configuration->get('WHRECOMMENDATION_CRON_TOKEN');

        //check if token is valid
        if (Tools::getValue('token') != $token) {
            $this->ajaxRender("Invalid token\n");
            return;
        }

        $this->generateRecommandationDataUserBased();
        $this->generateRecommendationDataItemBased();
        $this->generateRecommendationDataDBSCan();
    }

    private function generateRecommandationDataUserBased(){
        //Data retrieval
        $columns = ["Ballon de football","Chaussures de football","Maillot de football","Shorts de football","Protège-tibias","Ballon de basketball","Chaussures de basketball","Maillot de basketball","Short de basketball","Bandeau","Raquette de tennis","Balles de tennis","Chaussures de tennis","Polo de tennis","Bandeau de tennis","Maillot de bain","Lunettes de natation","Bonnet de bain","Serviette de plage","Palmes"];
        $fileDataSet = _PS_MODULE_DIR_.'whrecommendation/dataset/orders_sport.csv';
        $dataset = Unlabeled::fromIterator(new CSV($fileDataSet, true, ","));
        $dataset->apply(new NumericStringConverter());
        $dataset->apply(new MissingDataImputer());
        $dataset = $dataset->deduplicate();

        
        //Le panier courant contient une chaussure de football
        $currentCart = [0,0,0,0,1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0];

        //Calcul des similarités entre le panier courant et les autres paniers
        $similarityMatrix = array();
        for ($i=0; $i<count($dataset->samples()); $i++) {
            $similarityMatrix[$i] = 1 - $this->distanceCosine($dataset->sample($i), $currentCart);
        }
        
        //Création d'un vecteur de recommandation vide
        $recommandation = array();
        for($i = 0; $i<count($columns); $i++){
            $recommendation[$i]=0;
        }

        //Calcul des probabilités d'achat pour chaque produit en calculant la moyenne pondérée par les similarités
        foreach ($similarityMatrix as $key => $value) {
            for($i = 0; $i<count($columns); $i++){
                $recommendation[$i]=$recommendation[$i]+$value*$dataset->sample($key)[$i];
            }
        }
        for($i = 0; $i<count($columns); $i++){
            $recommendation[$i]=$recommendation[$i]/array_sum($similarityMatrix);
        }

        //Tri des recommandations par probabilité d'achat décroissante
        arsort($recommendation);
        $recommandation = array_slice($recommendation, 0, NUMBER_RECOMMENDATION, true);
        
        $this->ajaxRender("Done\n");

    }

    private function generateRecommendationDataItemBased()
    {
        //Data retrieval
        $columns = ["Ballon de football","Chaussures de football","Maillot de football","Shorts de football","Protège-tibias","Ballon de basketball","Chaussures de basketball","Maillot de basketball","Short de basketball","Bandeau","Raquette de tennis","Balles de tennis","Chaussures de tennis","Polo de tennis","Bandeau de tennis","Maillot de bain","Lunettes de natation","Bonnet de bain","Serviette de plage","Palmes"];
        $fileDataSet = _PS_MODULE_DIR_.'whrecommendation/dataset/orders_sport.csv';
        $dataset = Unlabeled::fromIterator(new CSV($fileDataSet, true, ","));
        $dataset->apply(new NumericStringConverter());
        $dataset->apply(new MissingDataImputer());
        $dataset = $dataset->deduplicate();

        //Transposition du dataset
        $transposedDataSet = $this->transposeDataSet($dataset->samples());

        //Calcul de la distance entre chaque produit par rapport à sa présence dans les commandes
        $distanceMatrix = array();
        for ($i=0; $i<count($transposedDataSet); $i++) {
            for ($j=0; $j<count($transposedDataSet); $j++) {
                $distanceMatrix[$i][$j] = $this->distanceCosine($transposedDataSet[$i], $transposedDataSet[$j]);
            }
        }
        //Recommandation pour le polo de tennis (13e ligne du dataset)
        $recommandations = $distanceMatrix[13];
        asort($recommandations);
        $recommandations = array_slice($recommandations, 0, NUMBER_RECOMMENDATION, true);

        //Here we will generate the recommendation data
        $this->ajaxRender("Done\n");
    }

    private function generateRecommendationDataDBSCan()
    {
        //Data retrieval
        $columns = ["Ballon de football","Chaussures de football","Maillot de football","Shorts de football","Protège-tibias","Ballon de basketball","Chaussures de basketball","Maillot de basketball","Short de basketball","Bandeau","Raquette de tennis","Balles de tennis","Chaussures de tennis","Polo de tennis","Bandeau de tennis","Maillot de bain","Lunettes de natation","Bonnet de bain","Serviette de plage","Palmes"];
        $fileDataSet = _PS_MODULE_DIR_.'whrecommendation/dataset/orders_sport.csv';
        $dataset = Unlabeled::fromIterator(new CSV($fileDataSet, true, ","));
        $dataset->apply(new NumericStringConverter());
        $dataset->apply(new MissingDataImputer());
        $dataset = $dataset->deduplicate();
        $dataset = Unlabeled::quick($this->transposeDataSet($dataset->samples()));

        //DBSCAN
        $epsilon = 0.5;
        $minSamples = 5;
        $distance = new Cosine();

        $dbScan = new DBSCAN($epsilon, $minSamples, new BallTree(30, $distance));
        $clusters = $dbScan->predict($dataset);

        //Regoupement en clusters
        $clusteredData = array();
        foreach ($clusters as $key => $value) {
            if(!isset($clusteredData[$value])){
                $clusteredData[$value] = array();
            }
            array_push($clusteredData[$value], $key);
        }

        //Here we will generate the recommendation data
        $this->ajaxRender("Done\n");
        
    }

    private function distanceCosine($a, $b){
        $cosineDistance = new Cosine();
        return $cosineDistance->compute($a, $b);
    }

    private function transposeDataSet($data){
        //Transpose the dataset
        $transposedData = array();
        for ($i=0; $i<count($data); $i++) {
            for ($j=0; $j<count($data[$i]); $j++) {
                $transposedData[$j][$i] = $data[$i][$j];
            }
        }
        return $transposedData;
    }

    private function printDataDescription($dataset, $columns)
    {
        $report = $dataset->describe();
        //Display the statistics report in a table
        $statsReport = '<table border="1">';
        $statsReport .= '<tr><th>Feature</th><th>Count</th><th>Mean</th><th>Standard Deviation</th><th>Min</th><th>Max</th></tr>';
        foreach ($report as $feature => $stats) {
            $statsReport .= '<tr>';
            $statsReport .= '<td>' . $columns[$feature] . '</td>';
            $statsReport .= '<td>' . $dataset->numSamples() . '</td>';
            $statsReport .= '<td>' . $stats['mean'] . '</td>';
            $statsReport .= '<td>' . $stats['standard deviation'] . '</td>';
            $statsReport .= '<td>' . $stats['min'] . '</td>';
            $statsReport .= '<td>' . $stats['max'] . '</td>';
            $statsReport .= '</tr>';
        }
        $statsReport .= '</table>';
        $this->ajaxRender($statsReport);
    }
}