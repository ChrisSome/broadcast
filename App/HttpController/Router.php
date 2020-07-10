<?php
namespace App\HttpController;

use EasySwoole\Http\AbstractInterface\AbstractRouter;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\template\Render;
use FastRoute\RouteCollector;

class Router extends AbstractRouter
{
    public function initialize(RouteCollector $routes)
    {
        // 未找到路由对应的方法
        $this->setMethodNotAllowCallBack(function (Request $request, Response $response) {
            var_dump('未找到路由对应的方法');
            var_dump($request->getUri()->getPath());
            $response->write(Render::getInstance()->render('default.404'));
            $response->withStatus(404);
        });

        // 未找到路由匹配
        $this->setRouterNotFoundCallBack(function (Request $request, Response $response) {
            var_dump('未找到路由匹配');
            var_dump($request->getUri()->getPath());
            $response->write(Render::getInstance()->render('default.404'));
            $response->withStatus(404);
        });

        $routes->addGroup('/admin', function (RouteCollector $route) {
            $route->get('/', '/Admin/Index');
            $route->get('/index_context', '/Admin/Index/indexContext');
            $route->get('/login', '/Admin/Login');
            $route->get('/logout', '/Admin/Login/logout');
            $route->post('/login', '/Admin/Login/login');
            $route->get('/verify', '/Admin/Login/verify');
            $route->post('/login_log', '/Admin/Index/loginLog');
            $route->post('/upload', '/Admin/Upload');
            $route->get('/version', '/Admin/Index/version');

            // 管理员
            $route->addGroup('/auth', function (RouteCollector $r) {
                $r->get('', '/Admin/Auth/User');
                $r->post('/get_all', '/Admin/Auth/User/getAll');
                $r->get('/info', '/Admin/Auth/User/info');
                $r->get('/add', '/Admin/Auth/User/add');
                $r->post('/add', '/Admin/Auth/User/addData');

                $r->get('/edit/{id:\d+}', '/Admin/Auth/User/edit');
                $r->post('/edit/{id:\d+}', '/Admin/Auth/User/editData');

                $r->get('/pwd','/Admin/Auth/User/editPwd');
                $r->post('/pwd','/Admin/Auth/User/editPwdData');

                // $r->get('/info','/Admin/Auth/User/info');
                // $r->post('/info','/Admin/Auth/User/infoData');

                $r->post('/set/{id:\d+}', '/Admin/Auth/User/set');
                $r->post('/del/{id:\d+}', '/Admin/Auth/User/del');
            });

            // 角色
            $route->addGroup('/role', function (RouteCollector $r) {
                $r->get('', '/Admin/Auth/Role');
                $r->post('/get_all', '/Admin/Auth/Role/getAll');

                $r->get('/add', '/Admin/Auth/Role/add');
                $r->post('/add', '/Admin/Auth/Role/addData');

                $r->get('/edit/{id:\d+}', '/Admin/Auth/Role/edit');
                $r->post('/edit/{id:\d+}', '/Admin/Auth/Role/editData');

                $r->get('/edit_rule/{id:\d+}', '/Admin/Auth/Role/editRule');
                $r->post('/edit_rule/{id:\d+}', '/Admin/Auth/Role/editRuleData');
                $r->post('/set/{id:\d+}', '/Admin/Auth/Role/set');

                $r->post('/del/{id:\d+}', '/Admin/Auth/Role/del');
            });

            // 权限
            $route->addGroup('/rule', function (RouteCollector $r) {
                $r->addRoute(['GET'], '', '/Admin/Auth/Rule');
                $r->post('/get_all', '/Admin/Auth/Rule/getAll');

                $r->get('/add', '/Admin/Auth/Rule/add');
                $r->post('/add', '/Admin/Auth/Rule/addData');

                // 添加子节点
                $r->get('/add/{id:\d+}', '/Admin/Auth/Rule/addChild');
                $r->post('/add/{id:\d+}', '/Admin/Auth/Rule/addChildData');

                $r->get('/edit/{id:\d+}', '/Admin/Auth/Rule/edit');
                $r->post('/edit/{id:\d+}', '/Admin/Auth/Rule/editData');
                $r->post('/set/{id:\d+}', '/Admin/Auth/Rule/set');

                $r->post('/del/{id:\d+}', '/Admin/Auth/Rule/del');
            });

            //直播源管理
            $route->addGroup('/core', function (RouteCollector $r) {
                $r->addRoute(['GET'], '/play', '/Admin/Core/Play');
                $r->addRoute(['POST'], '/play/list', '/Admin/Core/play/getAll');

                // 添加子节点
                $r->get('/play/add', '/Admin/Core/Play/add');
                $r->post('/play/add', '/Admin/Core/Play/addData');

                $r->get('/play/edit/{id:\d+}', '/Admin/Core/Play/edit');
                $r->post('/play/edit/{id:\d+}', '/Admin/Core/Play/editData');
                $r->post('/play/set/{id:\d+}', '/Admin/Core/Play/set');

                $r->post('/play/del/{id:\d+}', '/Admin/Core/Play/del');
            });
            //用户管理
            $route->addGroup('/user', function (RouteCollector $r) {
                $r->addRoute(['GET'], '', '/Admin/User/User');
                $r->addRoute(['POST'], '/list', '/Admin/User/User/getAll');

                //用户信息审核
                $r->get('/apply', 'Admin/User/User/apply');
                $r->post('/apply', 'Admin/User/User/apply');
                $r->post('/userApply/{id:\d+}/pre_status/{pre_status:\d+}', 'Admin/User/User/userApply');
                // 添加子节点
                $r->get('/add', '/Admin/User/User/add');
                $r->post('/add', '/Admin/User/User/addData');

                $r->get('/edit/{id:\d+}', '/Admin/User/User/edit');
                $r->post('/edit/{id:\d+}', '/Admin/User/User/editData');
                $r->post('/set/{id:\d+}', '/Admin/User/User/set');

                $r->post('/del/{id:\d+}', '/Admin/User/User/del');
                $r->post('/is_repeat', '/Admin/User/User/checkIsRepeat');


                //帖子管理
                $r->addRoute(['GET'], '/post', '/Admin/User/Post');
                $r->addRoute(['POST'], '/post/list', '/Admin/User/Post/getAll');
                //帖子举报

                $r->addRoute(['GET'], '/post/accusation', '/Admin/User/Post/postAccusation');
                $r->addRoute(['POST'], '/post/accusation', '/Admin/User/Post/getAll');
                //帖子审核
                $r->addRoute(['GET'], '/post/examine', '/Admin/User/Post/postExamine');
                $r->addRoute(['POST'], '/post/examine', '/Admin/User/Post/getAll');

                $r->get('/post/edit/{id:\d+}', '/Admin/User/Post/edit');
                $r->post('/post/edit/{id:\d+}', '/Admin/User/Post/editData');
                $r->post('/post/set/{id:\d+}', '/Admin/User/Post/set');
                $r->post('/post/del/{id:\d+}', '/Admin/User/Post/del');
                $r->post('/post/confirm/{id:\d+}', '/Admin/User/Post/confirm');
                $r->post('/post/setTop/{id:\d+}', '/Admin/User/Post/setTop');
                $r->get('/post/comment/{id:\d+}', '/Admin/User/Comment/index');
                $r->post('/post/comment/list/{id:\d+}', '/Admin/User/Comment/getAll');
                $r->post('/post/comment/del/{id:\d+}', '/Admin/User/Comment/del');
            });

            //聊天管理
            $route->addGroup('/content', function (RouteCollector $r) {
                $r->addRoute(['GET'], '', '/Admin/Talking/Content');
                $r->addRoute(['POST'], '/list', '/Admin/Talking/Content/getAll');
//                $r->addRoute(['GET'], '', '/Admin/Talking/Content/getAll');


            });
            //配置累类
            $route->addGroup('/setting', function (RouteCollector $r) {
                $r->addRoute(['GET'], '/user/option', '/Admin/Setting/Option');
                $r->addRoute(['GET'], '/option/list', '/Admin/Setting/Option/getList');
                $r->get('/option/edit/{id:\d+}', '/Admin/Setting/Option/edit');
                $r->post('/option/edit/{id:\d+}', '/Admin/Setting/Option/editData');
                $r->post('/option/set/{id:\d+}', '/Admin/Setting/Option/set');
                $r->post('/option/del/{id:\d+}', '/Admin/Setting/Option/del');
                //消息类型
                $r->addRoute(['GET'], '/category', '/Admin/Setting/Category');
                $r->addRoute(['POST'], '/cate/list', '/Admin/Setting/Category/getAll');
                $r->addRoute(['GET'], '/cate/add', '/Admin/Setting/Category/add');
                $r->addRoute(['POST'], '/cate/add', '/Admin/Setting/Category/addData');
                // 添加子节点
                $r->get('/cate/add/{id:\d+}', '/Admin/Setting/Category/addChild');
                $r->post('/cate/add/{id:\d+}', '/Admin/Setting/Category/addChildData');
                $r->get('/cate/edit/{id:\d+}', '/Admin/Setting/Category/edit');
                $r->post('/cate/edit/{id:\d+}', '/Admin/Setting/Category/editData');
                $r->post('/cate/set/{id:\d+}', '/Admin/Setting/Category/set');
                $r->post('/cate/del/{id:\d+}', '/Admin/Setting/Category/del');

                //乱七八糟
                $r->get('/privacy', 'Admin/Setting/System/privacy');
                $r->post('/privacy', 'Admin/Setting/System/privacy');
                $r->get('/problem', 'Admin/Setting/System/problem');
                $r->post('/problem', 'Admin/Setting/System/problem');
                $r->get('/notice', 'Admin/Setting/System/notice');
                $r->post('/notice', 'Admin/Setting/System/notice');
                $r->get('/sensitive', 'Admin/Setting/System/sensitive');
                $r->post('/sensitive', 'Admin/Setting/System/sensitive');
                //消息列表
                $r->addRoute(['GET'], '/message', '/Admin/Setting/Message');
                $r->addRoute(['POST'], '/message/list', '/Admin/Setting/Message/getAll');
                $r->addRoute(['GET'], '/message/add', '/Admin/Setting/Message/add');
                $r->addRoute(['POST'], '/message/add', '/Admin/Setting/Message/addData');
                $r->get('/message/edit/{id:\d+}', '/Admin/Setting/Message/edit');
                $r->post('/message/edit/{id:\d+}', '/Admin/Setting/Message/editData');
                $r->post('/message/set/{id:\d+}', '/Admin/Setting/Message/set');
                $r->post('/message/del/{id:\d+}', '/Admin/Setting/Message/del');

                // 短信列表
                $r->get('/phonecode', 'Admin/Setting/Phonecode');
                $r->post('/phonecode', 'Admin/Setting/Phonecode');

                //系统配置项
                $r->get('/sys', 'Admin/Setting/System');
                $r->post('/sys/list', 'Admin/Setting/System/getAll');
                $r->get('/sys/add', 'Admin/Setting/System/add');
                $r->post('/sys/add', 'Admin/Setting/System/addData');
                $r->get('/sys/edit/{id:\d+}', 'Admin/Setting/System/edit');
                $r->post('/sys/edit/{id:\d+}', '/Admin/Setting/System/editData');
                $r->post('/sys/del/{id:\d+}', '/Admin/Setting/System/del');
            });
            //前台接口
            $route->addGroup('/api', function (RouteCollector $r) {
                $r->addRoute(['GET'], '/user/login', '/User/Login');
                $r->addRoute(['POST'], '/user/upload', '/User/Upload');
                $r->addRoute(['POST'], '/user/info', '/User/User/info');
                $r->addRoute(['POST'], '/user/operate', '/User/User/operate');  //用户信息更改
                $r->addRoute(['POST'], '/user/doLogin', '/User/Login/doLogin'); //登陆接口
                $r->addRoute(['POST'], '/user/thirdLogin', '/User/Login/thirdLogin'); //三方微信登陆
//                $r->addRoute(['POST'], '/user/get-phone-code', '/User/Login/getPhoneCode'); //获取验证码接口
                $r->addRoute(['GET'], '/user/get-phone-code', '/User/Login/userSendSmg'); //获取验证码接口
                $r->addRoute(['POST', 'GET'], '/user/logout', '/User/Login/doLogout'); //退出接口
                $r->addRoute(['GET'], '/broad/list', '/User/Broad');
                $r->addRoute(['POST'], '/user/option', '/User/Option/add'); //添加建议
                $r->addRoute(['GET'], '/user/personal', '/User/Personal/index');
                $r->addRoute(['GET'], '/system/message', '/User/System/index'); //公告列表
                $r->addRoute(['GET'], '/system/message/detail/{id:\d+}', '/User/System/detail'); //公告详情
                $r->addRoute(['GET'], '/user/post', '/User/Post/index'); //帖子页面
                $r->addRoute(['POST'], '/user/post/list', '/User/Post/getList'); //帖子列表
                $r->addRoute(['GET'], '/user/post/mine', '/User/Post/getList'); //我的帖子列表页面
                $r->addRoute(['POST'], '/user/post/mineList', '/User/Post/getMineList'); //我的帖子列表
                $r->addRoute(['POST'], '/user/post/add', '/User/Post/addPost'); //发布帖子
                $r->addRoute(['POST'], '/user/post/detail', '/User/Post/detail'); //帖子详情
                $r->addRoute(['POST'], '/user/post/comment', '/User/Post/comment'); //用户评论
                $r->addRoute(['POST'], '/user/post/operate', '/User/Post/operate'); //操作行为
                $r->addRoute(['POST'], '/user/comment/list/{id:\d+}', '/User/Post/detail'); //评论详情
                $r->addRoute(['POST'], '/user/edit-user', '/User/User/editUser'); //用户编辑资料
                $r->addRoute(['POST'], '/user/comment/getList', '/User/Post/commentList'); //用户发表的评论
                $r->addRoute(['POST'], '/user/userOperate/posts', '/User/User/userOperatePosts'); //用户操作帖子列表


                $r->addRoute(['POST'], '/user/post/del', '/User/Post/del'); //用户删除帖子
                $r->addRoute(['POST'], '/user/user/follow', '/User/User/userFollowings'); //关注用户
                $r->addRoute(['POST'], '/user/userOperate/fabolus', '/User/User/myFabolusInfo'); //用户被点赞的帖子及评论列表
                $r->addRoute(['POST'], '/user/user/replyComments', '/User/User/myReplys'); //回复给我的评论

                $r->addRoute(['GET'], '/user/websocket', 'User/WebSocket');


                //社区部分
                $r->addRoute(['GET'], '/community/pList', '/User/Community/pLists');
                $r->addRoute(['GET'], '/community/mess', '/User/Community/messAndRefinePosts');
                $r->addRoute(['GET'], '/community/followings', '/User/Community/myFollowings');   //我的关注列表

                $r->addRoute(['GET'], '/user/post/getChildComments', '/User/Post/getPostChildComments');   //我的关注列表
                $r->addRoute(['GET'], '/user/post/childComments', '/User/Post/childComment');   //二级评论列表
                $r->addRoute(['POST'], '/user/post/reprint', '/User/Post/rePrint');   //用户转载评论

            });



        });
    }
}
