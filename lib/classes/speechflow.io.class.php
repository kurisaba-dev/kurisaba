<?php

class SpeechflowIO {
    static public function supportedFormats()
    {
        // See https://docs.speechflow.io/#/?id=file-format
        return [ "aac", "amr", "ape", "flac", "m4a", "mp3", "ogg", "opus", "wav", "wma", "3gp", "asf", "avi", "flv", "mkv", "mov", "mp4", "mpeg", "mpg", "webm", "wmv", "rm", "rmvb" ];
    }

    public function __construct($API_KEY_ID, $API_KEY_SECRET, $FILE_PATH, $LANG = "en")
    {
        // Generate API KEY, see: https://docs.speechflow.io/#/?id=generate-api-key
        $this->API_KEY_ID = $API_KEY_ID;
        $this->API_KEY_SECRET = $API_KEY_SECRET;
        // The language code of the speech in media file.
        // See more lang code: https://docs.speechflow.io/#/?id=ap-lang-list
        $this->LANG = $LANG;
        // The local path or remote path of media file.
        $this->FILE_PATH = $FILE_PATH;

        // The translation result type.
        // 1, the default result type, the json format for sentences and words with begin time and end time.
        // 2, the json format for the generated subtitles with begin time and end time.
        // 3, the srt format for the generated subtitles with begin time and end time.
        // 4, the plain text format for transcription results without begin time and end time.
        $this->RESULT_TYPE = 4;
        $this->headers = array("keyId: " . $API_KEY_ID, "keySecret: " . $API_KEY_SECRET);
    }

    public function create()
    {
        $create_data = array("lang" => $this->LANG);
        $create_url = "https://api.speechflow.io/asr/file/v1/create";
        $create_data['remotePath'] = $this->FILE_PATH;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $create_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($create_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            $create_result = json_decode($response, true);
            if ($create_result["code"] == 10000) {
                return [$create_result["taskId"], "success"];
            } else {
                return ["", "create error", $create_result["msg"]];
            }
        } else {
            return ["", "create request failed", $http_code];
        }
    }

    public function query($task_id)
    {
        $query_url = "https://api.speechflow.io/asr/file/v1/query?taskId=" . $task_id . "&resultType=" . strval($this->RESULT_TYPE);
        while (true) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $query_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code == 200) {
                $query_result = json_decode($response, true);
                if ($query_result["code"] == 11000) {
                    return ["transcription result", $query_result['result']];
                } elseif ($query_result["code"] == 11001) {
                    sleep(3);
                    continue;
                } else {
                    return ["transcription error", $query_result['msg']];
                    break;
                }
            } else {
                return ["query request failed", $http_code];
            }
        }
    }

    public function main()
    {
        $task_id = create()[0];
        if ($task_id != "") {
            print_r(query($task_id));
        }
    }
}

?>