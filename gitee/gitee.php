<?php

namespace oauth\gitee;

class gitee
{
    public function meta()
    {
        return [
            'name'        => 'Gitee登录',
            'description' => '使用Gitee(码云)账号登录您的网站',
            'author'      => 'xkatld',
            'logo_url'    => 'gitee.svg',
        ];
    }

    public function config()
    {
        return [
            'Client ID' => [
                'type' => 'text',
                'name' => 'client_id',
                'desc' => '从Gitee开放平台获取的Client ID'
            ],
            'Client Secret' => [
                'type' => 'text',
                'name' => 'client_secret',
                'desc' => '从Gitee开放平台获取的Client Secret'
            ],
        ];
    }

    public function url($params)
    {
        if (empty($params['client_id']) || empty($params['callback'])) {
            throw new \Exception("Gitee OAuth 配置错误: 缺少 Client ID 或回调地址.");
        }

        $clientId = $params['client_id'];
        $redirectUri = $params['callback'];
        $scope = 'user_info'; // Gitee获取用户信息的常用Scope，默认是user_info, emails, enterprises, projects, pull_requests, issues, notes, keys, hooks
        // Gitee也支持state参数，建议在url()中生成并传递，然后在callback()中验证
        // $state = md5(uniqid(rand(), true));

        $authUrl = 'https://gitee.com/oauth/authorize?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope,
            // 'state' => $state,
        ]);

        return $authUrl;
    }

    public function callback($params)
    {
        if (empty($params['client_id']) || empty($params['client_secret']) || empty($params['code'])) {
             throw new \Exception("Gitee OAuth 回调错误: 缺少 Client ID, Client Secret, 或授权码.");
        }

        $clientId = $params['client_id'];
        $clientSecret = $params['client_secret'];
        $redirectUri = $params['callback'];
        $code = $params['code'];

        // 如果在url()中使用了state，这里需要验证
        // $state = $params['state'] ?? null;
        // if ($state === null || $state !== $_SESSION['oauth_state']) { // 假设state存储在SESSION中
        //     throw new \Exception("OAuth State mismatch.");
        // }
        // unset($_SESSION['oauth_state']);


        $tokenUrl = 'https://gitee.com/oauth/token';
        $tokenParams = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("获取 Gitee 访问令牌失败. HTTP Code: " . $httpCode . ", 错误: " . $error);
        }

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['access_token'])) {
            $errorMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? '未知错误';
            throw new \Exception("Gitee 访问令牌响应中未找到令牌: " . $errorMsg);
        }

        $accessToken = $tokenData['access_token'];

        $userInfoUrl = 'https://gitee.com/api/v5/user?access_token=' . $accessToken; // Gitee通常通过URL参数传递access_token

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Gitee API /v5/user endpoint does not explicitly require User-Agent like GitHub, but good practice
        // curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: ' . ($this->meta()['author'] ?? 'FinancialSystemOAuthPlugin')]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("获取 Gitee 用户信息失败. HTTP Code: " . $httpCode . ", 错误: " . $error);
        }

        $userData = json_decode($response, true);

        if (!isset($userData['id'])) {
             $errorMsg = $userData['message'] ?? '未知错误';
             throw new \Exception("Gitee 用户信息响应中未找到用户ID: " . $errorMsg);
        }

        $openid = (string) $userData['id'];
        $userInfo = [
            'username' => $userData['login'] ?? $userData['name'] ?? 'Gitee 用户',
            'sex'      => null, // Gitee API v5 user endpoint does not provide sex
            'province' => $userData['province'] ?? null, // Gitee has 'province' field
            'city'     => $userData['city'] ?? null,     // Gitee has 'city' field
            'avatar'   => $userData['avatar_url'] ?? null,
        ];

        $callbackBind = 'all';

        return [
            'openid'       => $openid,
            'data'         => $userInfo,
            'callbackBind' => $callbackBind,
        ];
    }
}
