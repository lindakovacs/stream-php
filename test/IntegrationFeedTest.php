<?php
namespace GetStream\Stream;

use DateTime;
use DateTimeZone;

class IntegrationTest extends \PHPUnit_Framework_TestCase
{
    protected $client;
    protected $user1;
    protected $aggregated2;
    protected $aggregated3;
    protected $flat3;

    protected function setUp()
    {
        $this->client = new Client(
            getenv('STREAM_API_KEY'),
            getenv('STREAM_API_SECRET'),
            'v1.0',
            getenv('STREAM_REGION')
        );
        $this->client->setLocation('qa');
        $this->client->timeout = 10000;
        $this->user1 = $this->client->feed('user', '11');
        $this->aggregated2 = $this->client->feed('aggregated', '22');
        $this->aggregated3 = $this->client->feed('aggregated', '33');
        $this->flat3 = $this->client->feed('flat', '33');
    }

    protected function tearDown()
    {
        $feeds = [$this->user1, $this->aggregated3, $this->aggregated3, $this->flat3];
        for ($f=0; $f < count($feeds); $f++) {
            $feeds[$f]->delete();
        }
        parent::tearDown(); // TODO: Change the autogenerated stub
    }

    public function testRedirectUrl()
    {
        $targetUrl = 'http://google.com/?a=1';
        $impression = [
          'content_list' => ['tweet:34349698', 'tweet:34349699', 'tweet:34349697'],
          'feed_id'      => 'flat:tommaso',
          'location'     => 'profile_page',
          'user_data'    => ['id' => 'bubbles'],
          'label'        => 'impression'
        ];
        $engagement = [
          'content'      => 'tweet:34349698',
          'feed_id'      => 'flat:tommaso',
          'location'     => 'profile_page',
          'user_data'    => ['id' => 'frank'],
          'label'        => 'click'
        ];
        $events = [$impression, $engagement];
        $finalUrl = $this->client->createRedirectUrl($targetUrl, $events);
    }

    public function testUpdateActivity()
    {
        $now = new DateTime('now', new DateTimeZone('Pacific/Nauru'));
        $activity = [
            'actor' => 1,
            'verb' => 'tweet',
            'object' => 1,
            'time' => $now->format(DateTime::ISO8601),
            'foreign_id' => 'batch1',
            'popularity' => 100
        ];

        $this->client->updateActivity($activity);
        $activity['popularity'] = 10;
        $this->client->updateActivity($activity);
    }

    public function testAddToMany()
    {
        $batcher = $this->client->batcher();
        $activityData = [
            'actor' => 1,
            'verb' => 'tweet',
            'object' => 1,
            'foreign_id' => 'batch1'
        ];
        $feeds = ['flat:ba1', 'user:ba1'];

        $batcher->addToMany($activityData, $feeds);
        $b1 = $this->client->feed('flat', 'ba1');
        $response = $b1->getActivities();
        $this->assertSame('batch1', $response['results'][0]['foreign_id']);
    }

    public function testFollowManyWithActivityCopyLimitZero()
    {
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1];
        $response = $this->client->feed('user', 'b11')->addActivity($activity_data);
        $batcher = $this->client->batcher();
        $follows = [
            ['source' => 'flat:b11', 'target' => 'user:b11'],
            ['source' => 'flat:b11', 'target' => 'user:b33']
        ];
        $batcher->followMany($follows, 0);
        sleep(5);
        $b11 = $this->client->feed('flat', 'b11');
        $response = $b11->following();
        $this->assertCount(2, $response['results']);
    }

    public function testFollowMany()
    {
        $batcher = $this->client->batcher();
        $follows = [
            ['source' => 'flat:b1', 'target' => 'user:b1'],
            ['source' => 'flat:b1', 'target' => 'user:b3']
        ];
        $batcher->followMany($follows);

        $b1 = $this->client->feed('flat', 'b1');
        $response = $b1->following();
        $this->assertCount(2, $response['results']);
    }

    // public function testSignedGetHttp()
    // {
    //     $batcher = $this->client->batcher();
    //     $batcher->test('GET');
    // }

    // public function testSignedPostHttp()
    // {
    //     $batcher = $this->client->batcher();
    //     $batcher->test('POST');
    // }

    public function testReadonlyToken()
    {
        $token = $this->user1->getReadonlyToken();
        $this->assertSame($token, "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhY3Rpb24iOiJyZWFkIiwiZmVlZF9pZCI6InVzZXIxMSIsInJlc291cmNlIjoiKiJ9.4Ynt_2KZTGNS3H_fcmVgLnZDzjRYj0vUm6hZ4PY2VPE");
    }

    public function testAddActivity()
    {
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1];
        $response = $this->user1->addActivity($activity_data);
        $activity_id = $response['id'];
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertCount(1, $activities);
        $this->assertSame($activities[0]['id'], $activity_id);
    }

    public function testAddActivities()
    {
        $activities = [
            ['actor' => 'multi1', 'verb' => 'tweet', 'object' => 1],
            ['actor' => 'multi2', 'verb' => 'tweet', 'object' => 2],
        ];
        $response = $this->user1->addActivities($activities);
        $activities = $this->user1->getActivities(0, 2)['results'];
        $this->assertCount(2, $activities);
        $actors = [$activities[0]['actor'], $activities[1]['actor']];
        $expected = ['multi1', 'multi2'];
        sort($expected);
        sort($actors);
        $this->assertSame($actors, $expected);
    }

    public function testAddActivityWithTime()
    {
        $now = new DateTime('now', new DateTimeZone('Pacific/Nauru'));
        $time = $now->format(DateTime::ISO8601);
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1, 'time' => $time];
        $response = $this->user1->addActivity($activity_data);
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertCount(1, $activities);
        $utc_time = new DateTime($activities[0]['time'], new DateTimeZone('UTC'));
        $this->assertSame($now->format('U'), $utc_time->format('U'));
    }

    public function testAddActivityWithArray()
    {
        $complex = ['tommaso', 'thierry'];
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1, 'complex' => $complex];
        $response = $this->user1->addActivity($activity_data);
        $activity_id = $response['id'];
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertCount(1, $activities);
        $this->assertSame($activities[0]['id'], $activity_id);
        sort($activities[0]['complex']);
        sort($complex);
        $this->assertSame($activities[0]['complex'], $complex);
    }

    public function testAddActivityWithAssocArray()
    {
        $complex = ['author' => 'tommaso', 'bcc' => 'thierry'];
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1, 'complex' => $complex];
        $response = $this->user1->addActivity($activity_data);
        $activity_id = $response['id'];
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertCount(1, $activities);
        $this->assertSame($activities[0]['id'], $activity_id);
        sort($activities[0]['complex']);
        sort($complex);
        $this->assertSame($activities[0]['complex'], $complex);
    }

    public function testRemoveActivity()
    {
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1];
        $response = $this->user1->addActivity($activity_data);
        $activity_id = $response['id'];
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertCount(1, $activities);
        $this->assertSame($activities[0]['id'], $activity_id);
        $this->user1->removeActivity($activity_id);
        sleep(2);
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertCount(0, $activities);
    }

    public function testRemoveActivityByForeignId()
    {
        $fid = 'post:42';
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1, 'foreign_id' => $fid];
        $response = $this->user1->addActivity($activity_data);
        $activity_id = $response['id'];
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertCount(1, $activities);
        $this->assertSame($activities[0]['id'], $activity_id);
        $this->assertSame($activities[0]['foreign_id'], $fid);
        $this->user1->removeActivity($fid, true);
        sleep(1);
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertCount(0, $activities);
    }

    public function testException()
    {
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1, 'new_field' => '42'];
        $response = $this->user1->addActivity($activity_data);
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertNotSame($activities[0]['new_field'], 42);
    }

    public function testFlatFollowUnfollow()
    {
        $this->user1->unfollowFeed('flat', '33');
        $activity_data = ['actor' => 1, 'verb' => 'FlatFollowUnfollow', 'object' => 1];
        $response = $this->flat3->addActivity($activity_data);
        $activity_id = $response['id'];
        $this->user1->followFeed('flat', '33');
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertCount(1, $activities);
        $this->assertSame($activities[0]['id'], $activity_id);
        $this->user1->unfollowFeed('flat', '33');
        sleep(2);
        $activities = $this->user1->getActivities(0, 10)['results'];
        $this->assertCount(0, $activities);
    }

    public function testFlatFollowUnfollowKeepHistory()
    {
        $now = new DateTime('now', new DateTimeZone('Pacific/Nauru'));
        $activity = [
            'actor' => 1,
            'verb' => 'tweet',
            'object' => 1,
            'time' => $now->format(DateTime::ISO8601),
        ];
        $feed = $this->client->feed('user', 'keephistory');
        $this->flat3->addActivity($activity);
        $feed->followFeed('flat', '33');
        $activities = $feed->getActivities(0, 1)['results'];
        $this->assertCount(1, $activities);
        $feed->unfollowFeed('flat', '33', true);
        sleep(2);
        $activities = $feed->getActivities(0, 1)['results'];
        $this->assertCount(1, $activities);
    }

    public function testFlatFollowUnfollowPrivate()
    {
        $secret = $this->client->feed('secret', '33');
        $this->user1->unfollowFeed('secret', '33');
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1];
        $response = $secret->addActivity($activity_data);
        $activity_id = $response['id'];
        $this->user1->followFeed('secret', '33');
        sleep(2);
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertCount(1, $activities);
        $this->assertSame($activities[0]['id'], $activity_id);
        $this->user1->unfollowFeed('secret', '33');
    }

    public function testDelete()
    {
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1];
        $this->user1->addActivity($activity_data);
        $activities = $this->user1->getActivities(0,1)['results'];
        $this->assertCount(1, $activities);
        $this->user1->delete();
        sleep(2);
        $activities = $this->user1->getActivities(0,1)['results'];
        $this->assertCount(0, $activities);
    }

    public function testGet()
    {
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1];
        $first_id = $this->user1->addActivity($activity_data)['id'];

        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 2];
        $second_id = $this->user1->addActivity($activity_data)['id'];

        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 3];
        $third_id = $this->user1->addActivity($activity_data)['id'];

        $activities = $this->user1->getActivities(0, 2)['results'];
        $this->assertCount(2, $activities);
        $this->assertSame($activities[0]['id'], $third_id);
        $this->assertSame($activities[1]['id'], $second_id);

        $activities = $this->user1->getActivities(1, 2)['results'];
        $this->assertSame($activities[0]['id'], $second_id);

        $id_offset =  ['id_lt' => $third_id];
        $activities = $this->user1->getActivities(0, 2, $id_offset)['results'];
        $this->assertSame($activities[0]['id'], $second_id);
    }

    public function testVerifyOff()
    {
        $this->user1->setGuzzleDefaultOption('verify', true);
        $activities = $this->user1->getActivities(0, 2);
    }

    public function testMarkRead()
    {
        $notification_feed = $this->client->feed('notification', 'php1');
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1];
        $notification_feed->addActivity($activity_data);

        $activity_data = ['actor' => 2, 'verb' => 'run', 'object' => 2];
        $notification_feed->addActivity($activity_data);

        $activity_data = ['actor' => 3, 'verb' => 'share', 'object' => 3];
        $notification_feed->addActivity($activity_data);

        $options = ['mark_read' => true];
        $activities = $notification_feed->getActivities(0, 2, $options)['results'];
        $this->assertCount(2, $activities);
        $this->assertFalse($activities[0]['is_read']);
        $this->assertFalse($activities[1]['is_read']);

        $activities = $notification_feed->getActivities(0, 2)['results'];
        $this->assertCount(2, $activities);
        $this->assertTrue($activities[0]['is_read']);
        $this->assertTrue($activities[1]['is_read']);
    }

    public function testMarkReadByIds()
    {
        $notification_feed = $this->client->feed('notification', 'php2');
        $activity_data = ['actor' => 1, 'verb' => 'tweet', 'object' => 1];
        $notification_feed->addActivity($activity_data);

        $activity_data = ['actor' => 2, 'verb' => 'run', 'object' => 2];
        $notification_feed->addActivity($activity_data);

        $activity_data = ['actor' => 3, 'verb' => 'share', 'object' => 3];
        $notification_feed->addActivity($activity_data);

        $options = ['mark_read' => []];
        $activities = $notification_feed->getActivities(0, 2)['results'];
        foreach ($activities as $activity) {
            $options['mark_read'][] = $activity['id'];
        }
        $this->assertFalse($activities[0]['is_read']);
        $this->assertFalse($activities[1]['is_read']);
        $notification_feed->getActivities(0, 3, $options);

        $activities = $notification_feed->getActivities(0, 3, $options)['results'];
        $this->assertTrue($activities[0]['is_read']);
        $this->assertTrue($activities[1]['is_read']);
        $this->assertFalse($activities[2]['is_read']);
    }

    public function testFollowersEmpty()
    {
        $lonely = $this->client->feed('flat', 'lonely');
        $response = $lonely->followers();
        $this->assertCount(0, $response['results']);
        $this->assertSame($response['results'], []);
    }

    public function testFollowersWithLimit()
    {
        $this->client->feed('flat', 'php43')->followFeed('flat', 'php42');
        $this->client->feed('flat', 'php44')->followFeed('flat', 'php42');
        $response = $this->client->feed('flat', 'php42')->followers(0, 2);
        $this->assertCount(2, $response['results']);
        $this->assertSame($response['results'][0]['feed_id'], 'flat:php44');
        $this->assertSame($response['results'][0]['target_id'], 'flat:php42');
    }

    public function testFollowingEmpty()
    {
        $lonely = $this->client->feed('flat', 'lonely');
        $response = $lonely->following();
        $this->assertCount(0, $response['results']);
        $this->assertSame($response['results'], []);
    }

    public function testFollowingsWithLimit()
    {
        $this->client->feed('flat', 'php43')->followFeed('flat', 'php42');
        $this->client->feed('flat', 'php43')->followFeed('flat','php44');
        $response = $this->client->feed('flat', 'php43')->following(0, 2);
        $this->assertCount(2, $response['results']);
        $this->assertSame($response['results'][0]['feed_id'], 'flat:php43');
        $this->assertSame($response['results'][0]['target_id'], 'flat:php44');
    }

    public function testDoIFollowEmpty()
    {
        $lonely = $this->client->feed('flat', 'lonely');
        $response = $lonely->following(0, 10, ['flat:asocial']);
        $this->assertCount(0, $response['results']);
        $this->assertSame($response['results'], []);
    }

    public function testDoIFollow()
    {
        $this->client->feed('flat', 'php43')->followFeed('flat', 'php42');
        $this->client->feed('flat', 'php43')->followFeed('flat', 'php44');
        $response = $this->client->feed('flat', 'php43')->following(0, 10, ['flat:php42']);
        $this->assertCount(1, $response['results']);
        $this->assertSame($response['results'][0]['feed_id'], 'flat:php43');
        $this->assertSame($response['results'][0]['target_id'], 'flat:php42');
    }

    public function testAddActivityTo()
    {
        $activity = [
            'actor' => 'multi1', 'verb' => 'tweet', 'object' => 1,
            'to'    => ['flat:remotefeed1'],
        ];
        $this->user1->addActivity($activity);
        $response = $this->client->feed('flat', 'remotefeed1')->getActivities(0, 2);
        $this->assertSame($response['results'][0]['actor'], 'multi1');
    }

    public function testAddActivitiesTo()
    {
        $activities = [
            [
                'actor' => 'many1', 'verb' => 'tweet', 'object' => 1,
                'to'    => ['flat:remotefeed2'],
            ],
            [
                'actor' => 'many2', 'verb' => 'tweet', 'object' => 1,
                'to'    => ['flat:remotefeed2'],
            ],
        ];
        $this->user1->addActivities($activities);
        $response = $this->client->feed('flat', 'remotefeed2')->getActivities(0, 2);
        $this->assertSame($response['results'][0]['actor'], 'many2');
    }
}
