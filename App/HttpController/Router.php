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

                $r->get('/competition/manage', '/Admin/Core/Competition/index');
                $r->get('/competition/list', '/Admin/Core/Competition/list');
                $r->get('/competition/info', '/Admin/Core/Competition/info');
                $r->post('/competition/add', '/Admin/Core/Competition/add');
                $r->post('/competition/del', '/Admin/Core/Competition/del');
                $r->post('/competition/save', '/Admin/Core/Competition/save');

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
                $r->get('/post/add', '/Admin/User/Post/add');
                $r->post('/post/add/{type:\d+}', '/Admin/User/Post/add');
                $r->post('/post/edit/{id:\d+}', '/Admin/User/Post/editData');
                $r->post('/post/set/{id:\d+}', '/Admin/User/Post/set');
                $r->post('/post/del/{id:\d+}', '/Admin/User/Post/del');
                $r->post('/post/confirm/{id:\d+}', '/Admin/User/Post/confirm');
                $r->post('/post/setTop/{id:\d+}', '/Admin/User/Post/setTop');
                $r->post('/post/setFine/{id:\d+}', '/Admin/User/Post/setFine');
                $r->get('/post/comment/{id:\d+}', '/Admin/User/Comment/index');
                $r->post('/post/comment/list/{id:\d+}', '/Admin/User/Comment/getAll');
                $r->post('/post/comment/del/{id:\d+}', '/Admin/User/Comment/del');

                $r->get('/post/category', '/Admin/User/PostCategory/getAll');
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
                $r->addRoute(['GET'], '/user/{id:\d+}', '/User/User/test');
                $r->addRoute(['POST'], '/user/upload', '/User/Upload');
                $r->addRoute(['POST'], '/user/ossUpload', '/User/Upload/ossUpload');  //oss上传
                $r->addRoute(['POST'], '/user/info', '/User/User/info');
                $r->addRoute(['POST'], '/user/operate', '/User/User/operate');  //用户信息更改
                $r->addRoute(['POST'], '/user/doLogin', '/User/Login/doLogin'); //登陆接口
                $r->addRoute(['POST'], '/user/thirdLogin', '/User/Login/thirdLogin'); //三方微信登陆
//                $r->addRoute(['POST'], '/user/get-phone-code', '/User/Login/getPhoneCode'); //获取验证码接口
                $r->addRoute(['GET'], '/user/get-phone-code', '/User/Login/userSendSmg'); //获取验证码接口
                $r->addRoute(['GET'], '/user/logout', '/User/Login/doLogout'); //退出接口
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
                $r->addRoute(['POST'], '/user/post/detail', '/User/Community/detail'); //帖子详情
                $r->addRoute(['POST'], '/user/post/comment', '/User/Post/comment'); //用户评论
                $r->addRoute(['POST'], '/user/post/operate', '/User/User/cpOperate'); //操作行为
                $r->addRoute(['POST'], '/user/comment/list/{id:\d+}', '/User/Post/detail'); //评论详情
                $r->addRoute(['POST'], '/user/edit-user', '/User/User/editUser'); //用户编辑资料
                $r->addRoute(['POST'], '/user/comment/getList', '/User/Post/commentList'); //用户发表的评论
                $r->addRoute(['POST'], '/user/userOperate/posts', '/User/User/userOperatePosts'); //用户操作帖子列表
                $r->addRoute(['POST'], '/user/setting/password', '/User/User/setPassword'); //用户注册完设定密码


                $r->addRoute(['POST'], '/user/post/del', '/User/Post/del'); //用户删除帖子
                $r->addRoute(['POST'], '/user/user/follow', '/User/User/userFollowings'); //关注用户
                $r->addRoute(['POST'], '/user/userOperate/fabolus', '/User/User/myFabolusInfo'); //用户被点赞的帖子及评论列表
                $r->addRoute(['POST'], '/user/user/replyComments', '/User/User/myReplys'); //回复给我的评论

                $r->addRoute(['GET'], '/user/websocket', 'User/WebSocket');
                $r->addRoute(['POST'], '/user/websocket/test', 'User/WebSocket/test');


                //社区部分
                $r->addRoute(['GET'], '/community/pList', '/User/Community/pLists');
                $r->addRoute(['GET'], '/community/mess', '/User/Community/messAndRefinePosts');
                $r->addRoute(['GET'], '/user/postCat', '/User/Community/postCat');   //分类帖子
                $r->addRoute(['GET'], '/user/post/childComments', '/User/Community/getAllChildComments');   //二级评论列表


                $r->addRoute(['GET'], '/community/followings', '/User/Community/myFollowings');   //我的关注列表


                $r->addRoute(['POST'], '/user/post/reprint', '/User/Post/rePrint');   //用户转载评论
                $r->addRoute(['GET'], '/user/myCenter', '/User/Community/myCenter');   //用户个人中心

                $r->addRoute(['GET'], '/user/myFollowUserPosts', '/User/User/myFollowUserPosts');   //我关注的人的帖子列表
                $r->addRoute(['GET'], '/user/messageList', '/User/User/userMessageList');   //用户消息列表
                $r->addRoute(['GET'], '/user/messInfo', '/User/User/userMessageInfo');   //用户消息列表
                $r->addRoute(['GET'], '/user/drafts', '/User/Post/drafts');   //帖子草稿
                $r->addRoute(['GET'], '/user/messageCenter', '/User/User/messageCenter');   //消息中心
                $r->addRoute(['GET'], '/user/messageCount', '/User/User/userMessTotal');   //消息数量

                $r->addRoute(['GET'], '/user/commentInfo', '/User/Community/commentInfo');   //评论详情
                $r->addRoute(['POST'], '/user/userFeedBack', '/User/User/userFeedBack');   //用户反馈
                $r->addRoute(['POST'], '/user/userSetting', '/User/User/userSetting');   //个人设置
                $r->addRoute(['GET'], '/system/hotreload', '/User/System/hotreload');   //
                $r->addRoute(['GET'], '/system/adImgs', '/User/System/adImgs');   //  启动页后的广告页
                $r->addRoute(['GET'], '/system/advertisement', '/User/System/advertisement');   //  启动页后的广告页
                $r->addRoute(['POST'], '/user/interestCompetition', '/User/User/userInterestCompetition');   //  用户关注赛事

                //比赛
                $r->addRoute(['POST'], '/footBall/match/interest', '/Match/FootballApi/userInterestMatch');   //用户关注比赛


                $r->addRoute(['GET'], '/footBall/getTeamList', '/Match/FootballMatch/teamList');   //球队列表
                $r->addRoute(['GET'], '/footBall/getTodayMatches', '/Match/FootballMatch/todayMatchList');   //今日比赛
                $r->addRoute(['GET'], '/footBall/getWeekMatches', '/Match/FootballMatch/getWeekMatches');   //未来一周比赛
                $r->addRoute(['GET'], '/footBall/getCompetitiones', '/Match/FootballMatch/competitionList');   //赛事列表
                $r->addRoute(['GET'], '/footBall/getStages', '/Match/FootballMatch/stageList');   //赛事阶段列表
                $r->addRoute(['GET'], '/footBall/getSteam', '/Match/FootballMatch/steamList');   //直播源
                $r->addRoute(['GET'], '/footBall/players', '/Match/FootballMatch/getPlayers');   //球员列表
                $r->addRoute(['GET'], '/footBall/clashHistory', '/Match/FootballMatch/clashHistory');   //获取比赛历史同赔统计数据列表
                $r->addRoute(['GET'], '/footBall/noticeUserMatch', '/Match/FootballMatch/noticeUserMatch');   //推送用户比赛即将开始 1次/分钟
                $r->addRoute(['GET'], '/footBall/deleteMatch', '/Match/FootballMatch/deleteMatch');   //取消或者删除的比赛
                $r->addRoute(['GET'], '/footBall/updateYesMatch', '/Match/FootballMatch/updateYesMatch');   //更新昨天比赛
                $r->addRoute(['GET'], '/footBall/matchTlive', '/Match/FootballMatch/matchTlive');   //推送

                //比赛后端api
//                $r->addRoute(['GET'], '/footBall/allMatches', '/Match/FootballMatch/test');   //赛事列表

                $r->addRoute(['GET'], '/footBall/competitionList', '/Match/FootballApi/getCompetition');   //赛事列表
                $r->addRoute(['GET'], '/footBall/matchList', '/Match/FootballApi/frontMatchList');   //比赛列表
                $r->addRoute(['GET'], '/footBall/matchListPlaying', '/Match/FootballApi/playingMatches');   //正在进行中比赛列表
                $r->addRoute(['GET'], '/footBall/userInterestMatchList', '/Match/FootballApi/userInterestMatchList');   //用户关注的比赛列表
                $r->addRoute(['GET'], '/footBall/matchSchedule', '/Match/FootballApi/matchSchedule');   //赛程列表
                $r->addRoute(['GET'], '/footBall/matchResult', '/Match/FootballApi/matchResult');   //赛果列表
                $r->addRoute(['GET'], '/footBall/lineUpDetail', '/Match/FootballApi/lineUpDetail');   //阵容详情
                $r->addRoute(['GET'], '/footBall/getClashHistory', '/Match/FootballApi/clashHistory');   //历史交锋
                $r->addRoute(['GET'], '/footBall/noticeInMatch', '/Match/FootballApi/noticeMatch');   //历史交锋
                $r->addRoute(['GET'], '/footBall/matchInfo', '/Match/FootballApi/getMatchInfo');   //比赛信息
                $r->addRoute(['GET'], '/footBall/test', '/Match/FootballMatch/test');   //历史交锋





            });


        });

    }
}
