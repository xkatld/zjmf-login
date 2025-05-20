<?php

namespace oauth\linuxdo;

class linuxdo
{
    public function meta()
    {
        return [
            'name'        => 'Linux.do登录',
            'description' => '使用Linux.do账号登录您的网站',
            'author'      => 'xkatld',
            'logo_url'    => 'linuxdo.svg',
        ];
    }

    public function config()
    {
        return [
            'Client ID' => [
                'type' => 'text',
                'name' => 'client_id',
                'desc' => '从connect.linux.do获取的Client ID'
            ],
            'Client Secret' => [
                'type' => 'text',
                'name' => 'client_secret',
                'desc' => '从connect.linux.do获取的Client Secret'
            ],
        ];
    }

    public function url($params)
    {
        if (empty($params['client_id']) || empty($params['callback'])) {
            throw new \Exception("Linux.do OAuth 配置错误: 缺少 Client ID 或回调地址.");
        }

        $clientId = $params['client_id'];
        $redirectUri = $params['callback'];

        $authUrl = 'https://connect.linux.do/oauth2/authorize?' . http_build_query([
             'response_type' => 'code',
             'client_id' => $clientId,
             'redirect_uri' => $redirectUri,
         ]);

        return $authUrl;
    }

    public function callback($params)
    {
        if (empty($params['client_id']) || empty($params['client_secret']) || empty($params['code'])) {
             throw new \Exception("Linux.do OAuth 回调错误: 缺少 Client ID, Client Secret, 或授权码.");
        }

        $clientId = $params['client_id'];
        $clientSecret = $params['client_secret'];
        $code = $params['code'];
        $redirectUriForToken = '';

        $tokenUrl = 'https://connect.linux.do/oauth2/token';

        $key = base64_encode($clientId . ':' . $clientSecret);

        $header = [
            'Authorization: Basic ' . $key,
            'Accept: application/json'
        ];

        $postData = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUriForToken,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("获取 Linux.do 访问令牌失败. HTTP Code: " . $httpCode . ", 错误: " . $error);
        }

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['access_token'])) {
            $errorMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? '未知错误';
            throw new \Exception("Linux.do 访问令牌响应中未找到令牌: " . $errorMsg);
        }

        $accessToken = $tokenData['access_token'];

        $userInfoUrl = 'https://connect.linux.do/api/user';

        $header = [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("获取 Linux.do 用户信息失败. HTTP Code: " . $httpCode . ", 错误: " . $error);
        }

        $userData = json_decode($response, true);

        if (!isset($userData['id'])) {
             $errorMsg = $userData['message'] ?? '未知错误';
             throw new \Exception("Linux.do 用户信息响应中未找到用户ID: " . $errorMsg);
        }

        $openid = (string) $userData['id'];
        $userInfo = [
            'username' => $userData['username'] ?? $userData['name'] ?? 'Linux.do 用户',
            'sex'      => null,
            'province' => null,
            'city'     => $userData['location'] ?? null,
            'avatar'   => $userData['avatar_url'] ?? null,
        ];

        if (empty($userInfo['avatar']) && !empty($userData['avatar_template'])) {
             $userInfo['avatar'] = 'https://linux.do' . str_replace('{size}', '120', $userData['avatar_template']);
        }

        $callbackBind = 'all';

        return [
            'openid'       => $openid,
            'data'         => $userInfo,
            'callbackBind' => $callbackBind,
        ];
    }
}
