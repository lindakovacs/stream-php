<?php
namespace GetStream\Stream;


class IntegrationTest extends \PHPUnit_Framework_TestCase
{

    protected $client;
    protected $user1;
    protected $aggregated2;
    protected $aggregated3;
    protected $flat3;

    protected function setUp() {
        $this->client = new Client('ahj2ndz7gsan', 'gthc2t9gh7pzq52f6cky8w4r4up9dr6rju9w3fjgmkv6cdvvav2ufe5fv7e2r9qy');
        $this->user1 = $this->client->feed('user:11');
        $this->aggregated2 = $this->client->feed('aggregated:22');
        $this->aggregated3 = $this->client->feed('aggregated:33');
        $this->flat3 = $this->client->feed('flat:33');
    }

    public function testAddActivity() {
        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 1);
        $response = $this->user1->addActivity($activity_data);
        $activity_id = $response['id'];
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertSame(count($activities), 1);
        $this->assertSame($activities[0]['id'], $activity_id);
    }

    public function testAddActivities() {
        $activities = array(
            array('actor'=> 'multi1', 'verb'=> 'tweet', 'object'=> 1),
            array('actor'=> 'multi2', 'verb'=> 'tweet', 'object'=> 2)
        );
        $response = $this->user1->addActivities($activities);
        $activities = $this->user1->getActivities(0, 2)['results'];
        $this->assertSame(count($activities), 2);
        $actors = array($activities[0]['actor'], $activities[1]['actor']);
        $expected = array('multi1', 'multi2');
        sort($expected);
        sort($actors);
        $this->assertSame($actors, $expected);
    }

    public function testAddActivityWithTime() {
        $now = new \DateTime("now", new \DateTimeZone('Pacific/Nauru'));
        $time = $now->format(\DateTime::ISO8601);
        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 1, 'time'=>$time);
        $response = $this->user1->addActivity($activity_data);
        $activity_id = $response['id'];
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertSame(count($activities), 1);
        $utc_time = new \DateTime($activities[0]['time'], new \DateTimeZone("UTC"));
        $this->assertSame($now->format('U'), $utc_time->format('U'));
    }

    public function testAddActivityWithArray() {
        $complex = array('tommaso', 'thierry');
        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 1, 'complex'=>$complex);
        $response = $this->user1->addActivity($activity_data);
        $activity_id = $response['id'];
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertSame(count($activities), 1);
        $this->assertSame($activities[0]['id'], $activity_id);
        sort($activities[0]['complex']);
        sort($complex);
        $this->assertSame($activities[0]['complex'], $complex);
    }

    public function testAddActivityWithAssocArray() {
        $complex = array('author' => 'tommaso', 'bcc' => 'thierry');
        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 1, 'complex'=>$complex);
        $response = $this->user1->addActivity($activity_data);
        $activity_id = $response['id'];
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertSame(count($activities), 1);
        $this->assertSame($activities[0]['id'], $activity_id);
        sort($activities[0]['complex']);
        sort($complex);
        $this->assertSame($activities[0]['complex'], $complex);
    }

    public function testRemoveActivity() {
        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 1);
        $response = $this->user1->addActivity($activity_data);
        $activity_id = $response['id'];
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertSame(count($activities), 1);
        $this->assertSame($activities[0]['id'], $activity_id);
        $this->user1->removeActivity($activity_id);
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertNotSame($activities[0]['id'], $activity_id);
    }

    public function testRemoveActivityByForeignId() {
        $fid = 'post:42';
        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 1, 'foreign_id'=> $fid);
        $response = $this->user1->addActivity($activity_data);
        $activity_id = $response['id'];
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertSame(count($activities), 1);
        $this->assertSame($activities[0]['id'], $activity_id);
        $this->assertSame($activities[0]['foreign_id'], $fid);
        $this->user1->removeActivity($fid, true);
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertNotSame($activities[0]['id'], $activity_id);
    }

    public function testException() {
        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 1, 'new_field' => '42');
        $response = $this->user1->addActivity($activity_data);
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertNotSame($activities[0]['new_field'], 42);
    }

    public function testFlatFollowUnfollow() {
        $this->user1->unfollowFeed('flat:33');
        sleep(3);
        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 1);
        $response = $this->flat3->addActivity($activity_data);
        $activity_id = $response['id'];
        $this->user1->followFeed('flat:33');
        sleep(5);
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertSame(count($activities), 1);
        $this->assertSame($activities[0]['id'], $activity_id);
        $this->user1->unfollowFeed('flat:33');
        sleep(5);
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertNotSame($activities[0]['id'], $activity_id);
    }

    public function testFlatFollowUnfollowPrivate() {
        $secret = $this->client->feed('secret:33');
        $this->user1->unfollowFeed('secret:33');
        sleep(3);
        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 1);
        $response = $secret->addActivity($activity_data);
        $activity_id = $response['id'];
        $this->user1->followFeed('secret:33');
        sleep(5);
        $activities = $this->user1->getActivities(0, 1)['results'];
        $this->assertSame(count($activities), 1);
        $this->assertSame($activities[0]['id'], $activity_id);
        $this->user1->unfollowFeed('secret:33');
    }

    public function testDelete() {
        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 1);
        $this->user1->addActivity($activity_data);
        $activities = $this->user1->getActivities(0,1)['results'];
        $this->assertSame(count($activities), 1);
        $this->user1->delete();
        $activities = $this->user1->getActivities(0,1)['results'];
        $this->assertSame(count($activities), 0);
    }

    public function testGet() {
        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 1);
        $first_id = $this->user1->addActivity($activity_data)['id'];

        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 2);
        $second_id = $this->user1->addActivity($activity_data)['id'];

        $activity_data = array('actor'=> 1, 'verb'=> 'tweet', 'object'=> 3);
        $third_id = $this->user1->addActivity($activity_data)['id'];

        $activities = $this->user1->getActivities(0, 2)['results'];
        $this->assertSame(count($activities), 2);
        $this->assertSame($activities[0]['id'], $third_id);
        $this->assertSame($activities[1]['id'], $second_id);

        $activities = $this->user1->getActivities(1, 2)['results'];
        $this->assertSame($activities[0]['id'], $second_id);

        $id_offset =  array('id_lt'=>$third_id);
        $activities = $this->user1->getActivities(0, 2, $id_offset)['results'];
        $this->assertSame($activities[0]['id'], $second_id);

    }

    public function testFollowersEmpty() {
        $lonely = $this->client->feed('flat:lonely');
        $response = $lonely->followers();
        $this->assertSame(count($response['results']), 0);
        $this->assertSame($response['results'], []);
    }

    public function testFollowersWithLimit() {
        $this->client->feed('flat:php43')->followFeed('flat:php42');
        $this->client->feed('flat:php44')->followFeed('flat:php42');
        $response = $this->client->feed('flat:php42')->followers(0, 2);
        $this->assertSame(count($response['results']), 2);
        $this->assertSame($response['results'][0]['feed_id'], 'flat:php44');
        $this->assertSame($response['results'][0]['target_id'], 'flat:php42');
    }

    public function testFollowingEmpty() {
        $lonely = $this->client->feed('flat:lonely');
        $response = $lonely->following();
        $this->assertSame(count($response['results']), 0);
        $this->assertSame($response['results'], []);
    }

    public function testFollowingsWithLimit() {
        $this->client->feed('flat:php43')->followFeed('flat:php42');
        $this->client->feed('flat:php43')->followFeed('flat:php44');
        $response = $this->client->feed('flat:php43')->following(0, 2);
        $this->assertSame(count($response['results']), 2);
        $this->assertSame($response['results'][0]['feed_id'], 'flat:php43');
        $this->assertSame($response['results'][0]['target_id'], 'flat:php44');
    }

    public function testDoIFollowEmpty() {
        $lonely = $this->client->feed('flat:lonely');
        $response = $lonely->following(0, 10, array('flat:asocial'));
        $this->assertSame(count($response['results']), 0);
        $this->assertSame($response['results'], []);
    }

    public function testDoIFollow() {
        $this->client->feed('flat:php43')->followFeed('flat:php42');
        $this->client->feed('flat:php43')->followFeed('flat:php44');
        $response = $this->client->feed('flat:php43')->following(0, 10, array('flat:php42'));
        $this->assertSame(count($response['results']), 1);
        $this->assertSame($response['results'][0]['feed_id'], 'flat:php43');
        $this->assertSame($response['results'][0]['target_id'], 'flat:php42');
    }

    public function testAddActivityTo() {
        $activity = array(
            'actor'=> 'multi1', 'verb'=> 'tweet', 'object'=> 1,
            'to'=> array('flat:remotefeed1')
        );
        $this->user1->addActivity($activity);
        $response = $this->client->feed('flat:remotefeed1')->getActivities(0, 2);
        $this->assertSame($response['results'][0]['actor'], 'multi1');       
    }

    public function testAddActivitiesTo() {
        $activities = array(
            array(
                'actor'=> 'many1', 'verb'=> 'tweet', 'object'=> 1,
                'to'=> array('flat:remotefeed2')
            ),
            array(
                'actor'=> 'many2', 'verb'=> 'tweet', 'object'=> 1,
                'to'=> array('flat:remotefeed2')
            )
        );
        $this->user1->addActivities($activities);
        $response = $this->client->feed('flat:remotefeed2')->getActivities(0, 2);
        $this->assertSame($response['results'][0]['actor'], 'many2');
    }

}