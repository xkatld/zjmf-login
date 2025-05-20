<?php

namespace oauth\github;

/**
 * GitHub第三方登录插件
 */
class github
{
    /**
     * 插件信息函数
     */
    public function meta()
    {
        return [
            'name'        => 'GitHub登录',
            'description' => '使用GitHub账号登录您的网站',
            'author'      => 'xkatld',
            'logo_url'    => 'github.svg',
        ];
    }

    /**
     * 插件接口配置信息函数
     */
    public function config()
    {
        return [
            'Client ID' => [
                'type' => 'text',
                'name' => 'client_id', // 将作为参数键名
                'desc' => '从GitHub OAuth Apps或GitHub Apps获取的Client ID'
            ],
            'Client Secret' => [
                'type' => 'text',
                'name' => 'client_secret', // 将作为参数键名
                'desc' => '从GitHub OAuth Apps或GitHub Apps获取的Client Secret'
            ],
        ];
    }

    /**
     * 生成授权地址函数
     *
     * @param array $params 包含接口配置参数和回调地址 ($params['callback'])
     * @return string GitHub授权页面的URL
     * @throws \Exception
     */
    public function url($params)
    {
        if (empty($params['client_id']) || empty($params['callback'])) {
            throw new \Exception("GitHub OAuth configuration error: client_id or callback URL is missing.");
        }

        $clientId = $params['client_id'];
        $redirectUri = $params['callback'];
        $scope = 'read:user'; // 请求读取基本用户信息

        // 构造GitHub授权URL
        // 建议加入 state 参数防范 CSRF，但示例中省略
        $authUrl = 'https://github.com/login/oauth/authorize?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
            // 'state' => $params['state'] ?? 'your_generated_state',
        ]);

        return $authUrl;
    }

    /**
     * 回调地址处理函数
     *
     * @param array $params 包含接口配置参数、回调地址、第三方返回参数 (如 $params['code'])
     * @return array 统一格式的用户信息数组 ('openid', 'data', 'callbackBind')
     * @throws \Exception
     */
    public function callback($params)
    {
        if (empty($params['client_id']) || empty($params['client_secret']) || empty($params['code'])) {
             throw new \Exception("GitHub OAuth callback error: Missing client_id, client_secret, or code.");
        }

        $clientId = $params['client_id'];
        $clientSecret = $params['client_secret'];
        $redirectUri = $params['callback'];
        $code = $params['code']; // GitHub返回的授权码

        // Step 1: 使用 code 交换 access_token
        $tokenUrl = 'https://github.com/login/oauth/access_token';
        $tokenParams = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ];

        $headers = ['Accept: application/json'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenParams));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // 生产环境建议开启并配置CA证书
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("Failed to get GitHub access token. HTTP Code: " . $httpCode . ", Error: " . $error);
        }

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['access_token'])) {
            $errorMsg = $tokenData['error_description'] ?? $tokenData['error'] ?? 'Unknown error';
            throw new \Exception("GitHub access token not found in response: " . $errorMsg);
        }

        $accessToken = $tokenData['access_token'];

        // Step 2: 使用 access_token 获取用户信息
        $userInfoUrl = 'https://api.github.com/user';
        $headers = [
            'Authorization: token ' . $accessToken,
            'User-Agent: ' . ($this->meta()['author'] ?? 'FinancialSystemOAuthPlugin'), // GitHub API requires User-Agent
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new \Exception("Failed to get GitHub user info. HTTP Code: " . $httpCode . ", Error: " . $error);
        }

        $userData = json_decode($response, true);

        if (!isset($userData['id'])) {
             $errorMsg = $userData['message'] ?? 'Unknown error';
             throw new \Exception("GitHub user ID not found in response: " . $errorMsg);
        }

        // 准备返回数据，符合规范要求
        $openid = (string) $userData['id']; // GitHub user ID
        $userInfo = [
            'username' => $userData['login'] ?? $userData['name'] ?? 'GitHub User',
            'sex'      => null, // GitHub API不提供性别
            'province' => $userData['location'] ?? null, // 可用location字段
            'city'     => null, // GitHub API不提供城市
            'avatar'   => $userData['avatar_url'] ?? null,
        ];

        // 控制绑定流程的行为
        $callbackBind = 'all'; // all, bind_mobile, bind_email, login

        return [
            'openid'       => $openid, // 必须
            'data'         => $userInfo, // 可选
            'callbackBind' => $callbackBind, // 可选
        ];
    }
}
