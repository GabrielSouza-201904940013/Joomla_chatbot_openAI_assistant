<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Uri\Uri;
use Joomla\String\StringHelper;


class plgsystemChatbotopenaiassistant extends JPlugin
{
    protected $app;
    
    public function onAfterRender()
    {
        $app = Factory::getApplication();
		if ($app->isClient("administrator")) { return; }
        $body = $this->app->getBody();
        $html = file_get_contents(__DIR__ . '/assets/bot.html');
        $body = str_replace('</body>',  $html .  '</body>', $body );
        $this->app->setBody($body);
    }

    function onBeforeRender(){
		$doc = Factory::getDocument();
        $doc->addStyleSheet('plugins/system/chatbotopenaiassistant/assets/bot.css');
        $doc->addScript('plugins/system/chatbotopenaiassistant/assets/bot.js');
        
        JHtml::_('jquery.framework');
    }

    private function CurlRequest($url, $method = 'POST', $body = null)
    {
        $apiKey = StringHelper::trim($this->params->get('APIkey',''));

        if(empty($apiKey)) {
            return json_encode(['error' => 'sem APIkey '.$apiKey]);
        } else {

            // Inicializa o cURL
            $ch = curl_init($url);

            // Configurações comuns do cURL
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                "Authorization: Bearer $apiKey",
                'OpenAI-Beta: assistants=v2'
            ]);

            // Define o método de requisição
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
                }
            } elseif ($method === 'GET') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            }

            // SSL para ambientes de teste
            //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            //curl_setopt($ch, CURLOPT_CAINFO, '/MAMP/bin/php/php7.4.33/extras/ssl/cacert.pem');

            // Executa a requisição e captura a resposta
            $response = curl_exec($ch);

            // Verifica se houve erro na requisição
            if ($response === false) {
                return json_encode(['error' => 'Erro na requisicao: ' . curl_error($ch)]);
            }

            curl_close($ch);
            return $response;
        }
    }

    public function onAjaxChatbotCreateThread()
    {
        $url = 'https://api.openai.com/v1/threads';

        $response = $this->CurlRequest($url, 'POST');

        $data =  json_decode($response,true);
        if(array_key_exists('error',$data)){
            echo json_encode(['error'=> $data['error']]);
        }else{
            $thread_id = $data["id"];
            echo json_encode(['thread_id'=> $thread_id]);
            Factory::getSession()->set('thread_ID', $thread_id);
        }

        Factory::getApplication()->close();
    }

    public function onAjaxChatbotCreateRun()
    {
        $input = Factory::getApplication() -> input;
        $message = $input -> json -> getString('message');

        $threadID = Factory::getSession()->get('thread_ID');
        if(empty($threadID)){$threadID = $input -> json -> getString('thread_id','null'); }
        //echo json_encode(['men' => $message]);

        if (empty($message)) {echo json_encode(['error' => 'Mensagem não fornecida']);
        } else {
            $response = '';

            // Create message ---------------------------------------------------------------
            $url1 = "https://api.openai.com/v1/threads/{$threadID}/messages";
            $body = [
                "role" => "user",
                "content" => $message
            ];
            $result = $this->CurlRequest($url1, 'POST', $body);
            $create = json_decode($result);


            // Create run -------------------------------------------------------------------
            $url2 = "https://api.openai.com/v1/threads/{$threadID}/runs";
            $body = [
                'assistant_id' => StringHelper::trim($this->params->get('assistantID',''))
            ];
            $result = $this->CurlRequest($url2, 'POST', $body);
            $createRun = json_decode($result, true);
            $run_id = $createRun['id'];

            
            // Retrieve run -----------------------------------------------------------------
            $interval = 2500000; // 0,5 segundos em microssegundos (500.000)
            do{
                $url3 = "https://api.openai.com/v1/threads/{$threadID}/runs/{$run_id}";
                $retrieve = json_decode($this->CurlRequest($url3, 'GET'), true);

                $response = $retrieve['status'];
                $erro = in_array($response, ['expired', 'cancelled', 'failed', 'incomplete']);
                if ($erro) { break; }

                usleep($interval);
            } while ($retrieve['status'] != 'completed');

            // List messages ----------------------------------------------------------------
            if($retrieve['status'] == 'completed') {
                $url4 = "https://api.openai.com/v1/threads/{$threadID}/messages";
                $result = $this->CurlRequest($url4, 'GET');
                $List = json_decode($result,true);
                $response = $List['data'][0]['content'][0]['text']['value'];
            }

            echo json_encode([
                'value' => $response,
                'threadID' => $threadID,
                'Create' => $create,
                'Run' => $createRun,
                'Retrieve' => $retrieve,
                'List' => $List
            ]);
        }

        Factory::getApplication()->close();
    }


    //try - testar conexão com o endpoint
    public function onAjaxChatbottry()
    {
        $input = Factory::getApplication()->input;
        $message = $input->json->getString('message');

        if (empty($message)) {
            echo json_encode(['error' => 'Mensagem nao fornecida', 'debug_message' => $message]);
        } else {
            echo json_encode(['value' => $message]);
        }

        Factory::getApplication()->close();
    }

}
