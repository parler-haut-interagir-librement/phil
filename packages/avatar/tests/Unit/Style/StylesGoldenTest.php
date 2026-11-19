<?php

declare(strict_types=1);

namespace Phil\Avatar\Tests\Unit\Style;

use Phil\Avatar\Canvas;
use Phil\Avatar\Initials;
use Phil\Avatar\Palette;
use Phil\Avatar\Random;
use Phil\Avatar\Style\PixelsStyle;
use Phil\Avatar\Style\ShapesStyle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The drawings of fixed seeds, as the digest of their markup: the choices of a style — ranges, counts, sizes, angles —
 * are what a seed gives back for good, so a change to one of them is a change to every avatar drawn.
 */
#[CoversClass(ShapesStyle::class)]
#[CoversClass(PixelsStyle::class)]
final class StylesGoldenTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function drawings(): iterable
    {
        yield 'shapes gold1' => ['shapes', 'gold1', '99be9e821a7eadaa8f404cbf73ea04972d36237c3959770f835eb908b1c6f5ce'];
        yield 'shapes gold2' => ['shapes', 'gold2', 'aeb85ff82ab71a30cbc17def405f9f72fe6c331b0b078396764c03e898e97702'];
        yield 'shapes gold3' => ['shapes', 'gold3', '50e850a4094e07f57d78c0d9354bed32bc8e16ddd59efbd0fe4c41eead00a051'];
        yield 'shapes gold4' => ['shapes', 'gold4', 'b9eb5e707db975afc03691f41169d8366f60ac0d322da0887ed2ffd87798c642'];
        yield 'shapes gold5' => ['shapes', 'gold5', '6a1bf144600866c711b6faf4a2e37f8a60398aa7aaf8cbae911148dc1280092c'];
        yield 'shapes gold6' => ['shapes', 'gold6', '1a0f192b7f0179dcf1d617eb6d2fa11c884bc7361bf6837ba85557b3453e8c08'];
        yield 'shapes gold7' => ['shapes', 'gold7', 'a369c3e65aaf229cef73e08a5bf05c2fddd16eb8bffbd72262f019720d84e1ab'];
        yield 'shapes gold8' => ['shapes', 'gold8', '91aa195444161b9df0565f45452e1f42af7d72bea79b15ec6ee0e36ebb1dcd52'];
        yield 'shapes gold9' => ['shapes', 'gold9', '9edd043e48bc7c2e3d12884e750352be34e47a20753d9d277cd02c1d4362e345'];
        yield 'shapes gold10' => ['shapes', 'gold10', '70d28fdcadf958db4df844c6bf02ca01610d5814867aa1d70d41e5baf522de15'];
        yield 'shapes gold11' => ['shapes', 'gold11', '171ddf63c58e93b8ebea977a57ea4b799b57732f1eb4f031073953119396d1a3'];
        yield 'shapes gold12' => ['shapes', 'gold12', '3181271597a0eb9901e66529bb694f463074aa502157d129d7b4a757b795e3e0'];
        yield 'shapes gold13' => ['shapes', 'gold13', '839dd73f5d1cca89e552fb8d30c3b7f501d92ff46f296a4494e84154ee0a1b7f'];
        yield 'shapes gold14' => ['shapes', 'gold14', '3ddb320f5bb38c0bf42bb56fc5278debc243867f7bad14266317dc9be23c9fe8'];
        yield 'shapes gold15' => ['shapes', 'gold15', '8f6f22dffb2d1cb27e0d2a59cb2eb3519e645c7f3ddba42595b2555d3fcfd259'];
        yield 'shapes gold16' => ['shapes', 'gold16', '206d5954ab67e2996783524ae051101f76714af5d8fd62bc949cf2902a183923'];
        yield 'shapes gold17' => ['shapes', 'gold17', '451b67ead3374ee22787243eb73f13a057311956abce0086f9deb97c5a49a873'];
        yield 'shapes gold18' => ['shapes', 'gold18', '1d7c11fa1f51edf979886918e4d9b65224faf8a5ef7d6a62e5ef46fc19142ce5'];
        yield 'shapes gold19' => ['shapes', 'gold19', '9343587a08a5cc90d0db92bd151a915384e3bfa3a38b2ea36c72e71f8243bb59'];
        yield 'shapes gold20' => ['shapes', 'gold20', '1d706a216a9448142457484b21cacd88aa2ca00ba22adc851afb6e6a06ee1ed8'];
        yield 'shapes gold21' => ['shapes', 'gold21', 'b44cc895bb56555093ce2c50ac34d25c417ace6e16808d4700d426b4cc5a76fb'];
        yield 'shapes gold22' => ['shapes', 'gold22', 'cdfb324a61aa6fef9ee518b804227ee32ca4ce0cd8221d97dc9a964417ccdeb3'];
        yield 'shapes gold23' => ['shapes', 'gold23', '19122c0c471f61bdc4dc36b86b318041bb053b639de52fe89aceaee2fba70d8a'];
        yield 'shapes gold24' => ['shapes', 'gold24', '97a6835df178976c5d10848b9bd39838c8e617cf5fbb37fc4169116e04e756f8'];
        yield 'shapes gold25' => ['shapes', 'gold25', '361a62d16092631d49b62c64298911d5f3033ae6f1c2bc42cad44dc458552f0c'];
        yield 'shapes gold26' => ['shapes', 'gold26', 'f25b98127c31299334376c28ef6a388b1d7c270db2089fc80815b2943053edcd'];
        yield 'shapes gold27' => ['shapes', 'gold27', 'd322e75489fdb31a825bfe5b2b199f4e31f3fa21e1ae30a301fd990abfb5ffcf'];
        yield 'shapes gold28' => ['shapes', 'gold28', '1ac15c131580b9c420ecf4675bb8d1f269442422384753004041dc3c259c2428'];
        yield 'shapes gold29' => ['shapes', 'gold29', 'f6bbf755daa985abb376b6eebcd74a4b113606884f2d21661ac0275903eaf252'];
        yield 'shapes gold30' => ['shapes', 'gold30', 'def54c126e2d2c7842c3f2ace8df9b1132f16dd2f7e928aaa85b603fb7c7225a'];
        yield 'shapes gold31' => ['shapes', 'gold31', 'a639248deaab3a8fa4930cab8816ee37104f5aaa3d9f4ca61a3b00d1865863b3'];
        yield 'shapes gold32' => ['shapes', 'gold32', 'c411a1e8623869559e74b6b694b2de274e5dea57bc8ef9d9862d3cc165069dd1'];
        yield 'shapes gold33' => ['shapes', 'gold33', '9a4061d44bdb8149db06225ceb1d02cdb56d71af0fbe5f8908b0f4edb28d90bd'];
        yield 'shapes gold34' => ['shapes', 'gold34', '5576089db2212165cf46c5e6e40e77e7b0f691c96ccf181ee57ffbfa3c70e47c'];
        yield 'shapes gold35' => ['shapes', 'gold35', '908edbbc34abf4f7924edec5d0f99561e7303ef6000dbbdd767eb479f7da9a07'];
        yield 'shapes gold36' => ['shapes', 'gold36', 'a9903d26a6f83c8bf9a46c97a2ec36eb3838f766b6be1ff37b656d235e3ada5b'];
        yield 'shapes gold37' => ['shapes', 'gold37', '64505c089529a7d87118470ee037cce978208324d3fead48e7c50bbaeab14d77'];
        yield 'shapes gold38' => ['shapes', 'gold38', '619b678a6abce7a7d548ed595720897039fc57232dfce1e94250425f46d09576'];
        yield 'shapes gold39' => ['shapes', 'gold39', '5c10c24acecf55a4a7cf5fbbc57ee1a339039239c4ab6e47c7305781e2825874'];
        yield 'shapes gold40' => ['shapes', 'gold40', '02d953be69616f08763c443581ef6514122e8e69e21ed5514e3919dbd609e943'];
        yield 'shapes gold41' => ['shapes', 'gold41', '0f70844148fb4eb3e0ef643ca6e79134339d8ef46d7dceaa7384135644cbe239'];
        yield 'shapes gold42' => ['shapes', 'gold42', '685ab03d77841551e0935e4ccde42e612dd85933e800414bc0703a740573fec4'];
        yield 'shapes gold43' => ['shapes', 'gold43', '8d6dbeeb885bf80441ccaf437af31694dedf28802dc990322183afe62d347d67'];
        yield 'shapes gold44' => ['shapes', 'gold44', '2900645957eb7f52be9095e53c7f3355b62f4d3ec76c4e2e73ba787b6c62053b'];
        yield 'shapes gold45' => ['shapes', 'gold45', 'eed9ae78ddeec630b8c41026c88da5d3bc3718e7f574acec1208e2dbdbfd8a76'];
        yield 'shapes gold46' => ['shapes', 'gold46', 'd4f850d7b2365bbe7edb4013eedd90d05ca84c998acbf9b6e62ed149a3265bbc'];
        yield 'shapes gold47' => ['shapes', 'gold47', '42daf008683ddf58d4ce89cc0f039e475027f1d0cc578fdbfda94b0a303cd2f2'];
        yield 'shapes gold48' => ['shapes', 'gold48', '91b40c2d77d5323bfa054e6821e2f6faa4837555a24c1e77bc1768f9cafd41da'];
        yield 'shapes gold49' => ['shapes', 'gold49', '72392eacb25e6f6aa71a902a5957922592ceb49aa332db27ed0cd493a947d374'];
        yield 'shapes gold50' => ['shapes', 'gold50', 'f46088ab66b515de112a035499a460e58e0c1d38403384e07189f91c30bb6c6b'];
        yield 'shapes gold51' => ['shapes', 'gold51', '93b648df784a0242e0d65a5ca5912483d8414df59965b08edb712fa84a23d090'];
        yield 'shapes gold52' => ['shapes', 'gold52', 'd5ed2a20c3c8714ff6a186ad5f563cbca4ab040626aaed0d3a973838d9c3ed81'];
        yield 'shapes gold53' => ['shapes', 'gold53', 'a41c6aede635adcced77a4a92d2c6a795097e7301b780fd3d5801d30ee54cd04'];
        yield 'shapes gold54' => ['shapes', 'gold54', 'e9655f2ab0f3f6a20b92488f5532c796565364235e8b9ddafb87941ae61499cd'];
        yield 'shapes gold55' => ['shapes', 'gold55', 'ad11672326589e1626d122422d389362a67448a0fa9f45fecbf242b7b9787f38'];
        yield 'shapes gold56' => ['shapes', 'gold56', '6aeb28fcb522bf51a3299f8e70a35d34e0f2e1e36da28187d4520ce2344aba01'];
        yield 'shapes gold57' => ['shapes', 'gold57', 'f2fb1060e40dcf01f06b7094b4a63cd662aef57b304cd61dc4761ba1d933a967'];
        yield 'shapes gold58' => ['shapes', 'gold58', 'b81f06373da7cdfea5185d5d9b576c4679d58a84adca8543902cdda69820110b'];
        yield 'shapes gold59' => ['shapes', 'gold59', '2aac7e3050cd52700fceea2b6337ec2b37774baa2f6cb16ac5bddb7830a01739'];
        yield 'shapes gold60' => ['shapes', 'gold60', 'ea9019bc5046332bc14faf879dc0fb58d7eda30d68fd995a8dc86d92678947e0'];
        yield 'pixels gold1' => ['pixels', 'gold1', '762a9bfc03a22b8b425800e85b25b0de92fb2ebc23065c7a989bd709e0dfba2c'];
        yield 'pixels gold2' => ['pixels', 'gold2', '05580b614261f05f3ddc6b22b3af614dd25938936499a41178ad97c81ee82b72'];
        yield 'pixels gold3' => ['pixels', 'gold3', 'acfed923126c8105604984f2b19a405fc6c57e9bfed8c4e3ad8529cf3254062c'];
        yield 'pixels gold4' => ['pixels', 'gold4', '01f5d79eb3d33b523044185c7d7cb595232b743ff2be9b5fae00de749f1049e9'];
        yield 'pixels gold5' => ['pixels', 'gold5', 'bb9cbc6aca9a1023dd4de906554470fcec41fce48c36bfc8ecc1a2bde4d6784b'];
        yield 'pixels gold6' => ['pixels', 'gold6', '2c37ab1c1e2f2f9bd49653fcf647a87a8933268b869a55577155dd0f145afaae'];
        yield 'pixels gold7' => ['pixels', 'gold7', '918f1e2cd90b1c69d368ca66c2517eb57db8d704235697037583971cd7d8ab00'];
        yield 'pixels gold8' => ['pixels', 'gold8', 'c478d9f05ec35f8a8f7a83fd9244bef22feef72be58122ff70e367547131648b'];
        yield 'pixels gold9' => ['pixels', 'gold9', '66d83cde3e464424bcf2c2fb640fef2f1e0c61e69677a94951cb25a15ec184eb'];
        yield 'pixels gold10' => ['pixels', 'gold10', '50b838daeec9aaece51b91267b95470e25c293f47cdd6fa8e032e33e46700908'];
        yield 'pixels gold11' => ['pixels', 'gold11', 'b7aa1e0f4bd35b8102d2ecf014ac16dee9645262aaf3c9db1ecf134920fe3809'];
        yield 'pixels gold12' => ['pixels', 'gold12', 'fcf2c905c02ec99a27fd1c8177e818a142eca4da2fb68f33a749addb0511f574'];
        yield 'pixels gold13' => ['pixels', 'gold13', '3dd640268304db90e47f32a801223c2243bdbc4dd303b6a597df34ead1f0ff0b'];
        yield 'pixels gold14' => ['pixels', 'gold14', '64a0b343ab8d3b8b0c924fb8c4df2c3baa91360f2ffc47233dc517a90489184e'];
        yield 'pixels gold15' => ['pixels', 'gold15', '91f3fbb8c2afc2c228b4b4b2f5e599f664ae54e34f99ffce48bd99fe36f61eb2'];
        yield 'pixels gold16' => ['pixels', 'gold16', '8a268fbfb6e12113f5ba283d4a2aec69993dcc07acb030bdac3c119ed2a4766b'];
        yield 'pixels gold17' => ['pixels', 'gold17', 'f9131218b5680a44ce07d3e796e0580e777a3cbdbe25c85fe32c6edcee259ed5'];
        yield 'pixels gold18' => ['pixels', 'gold18', 'd1f224b406e6645863cde473ceac5889660d9072c4eb306fe3db3b9dd777d2db'];
        yield 'pixels gold19' => ['pixels', 'gold19', '0abb00261c2b171b152f38e1f6e23eba178d0d2c199b56834623d639ccb7b40a'];
        yield 'pixels gold20' => ['pixels', 'gold20', 'e4832dcb4b0d0b48141b78c65c4e2e69b51272303c0f8ef3848c411b17385270'];
        yield 'pixels gold21' => ['pixels', 'gold21', '4e8a496f714ac82656df0b42115187a498b0d68013f3b5f8aa616b5b623c32ed'];
        yield 'pixels gold22' => ['pixels', 'gold22', '0692b6c1d450b97bd17b8c5195ab98d081cc5ed742861a1f457e400cd63cb955'];
        yield 'pixels gold23' => ['pixels', 'gold23', 'ffe12573bc29aff09788cf90b4f7ee9975fee45da6733a7c8d840060242b52c5'];
        yield 'pixels gold24' => ['pixels', 'gold24', 'f6134d34bb72fecdc0ea4a5cae67667c57677e86ae522f07f67277f5976ffc62'];
        yield 'pixels gold25' => ['pixels', 'gold25', '064402aef900f8c65a7d35ba1a7c384e9cdd49599846bb2917547cd4e752fbb7'];
        yield 'pixels gold26' => ['pixels', 'gold26', '98ab7a22c81b9318c409aa62e746219076c69560d51559016afab2b87141cd65'];
        yield 'pixels gold27' => ['pixels', 'gold27', 'c873127bd59ad08ad99ad51640a9623fc19080376356d0ce50b0fcf7fe157cae'];
        yield 'pixels gold28' => ['pixels', 'gold28', 'd5307f33b9b2176f6237415b8b1fde2a00282e7add2b8ae71130626291cc2f31'];
        yield 'pixels gold29' => ['pixels', 'gold29', '5d15558b538465a4f8d72cc45799b34b324cb014e74a1e7647563e295e493ba2'];
        yield 'pixels gold30' => ['pixels', 'gold30', '06129f4d382b49ff4da24b19db6eeb12c1c669ea548ec658873882e89a72a877'];
        yield 'pixels gold31' => ['pixels', 'gold31', 'efdcf6d1e5f4c0b680bfcbcf69058f0f91582a7df76aaae39d659b46b977da09'];
        yield 'pixels gold32' => ['pixels', 'gold32', '5d281240a73421469646fde94e3af9c90131dac1e9a6f4e3f584a9bd18ab1f94'];
        yield 'pixels gold33' => ['pixels', 'gold33', '0ab97626698e9c7ec5971480709a2e249bc475b907c02192afd18013fd650265'];
        yield 'pixels gold34' => ['pixels', 'gold34', '9ee75a2d86a866ac756b427735db45998558479ca69dc581857b31fd8c10e503'];
        yield 'pixels gold35' => ['pixels', 'gold35', 'c581025b15f2b13e8ee31708be5c98b90281d3d1d67f6f0cc6bb98bb28087ce3'];
        yield 'pixels gold36' => ['pixels', 'gold36', 'd62146f397d474850b6a9385140afd1b54a9ae0a1c555afe9f8b4581ad3f4c69'];
        yield 'pixels gold37' => ['pixels', 'gold37', 'c0a5fa3900e9e8153d66ade8bd7631f79d5a5fc0ee4f0e1eabfe6e39b2b2d75e'];
        yield 'pixels gold38' => ['pixels', 'gold38', 'a57f8276bd5d9f5f6ce9e197291d77c8c043f359772abf956eb819491c49c374'];
        yield 'pixels gold39' => ['pixels', 'gold39', '3e518252805993e4df20e26ba41c790ec32bb1bd2c8c1cb25bb7c863725aa9b5'];
        yield 'pixels gold40' => ['pixels', 'gold40', '35b76d508298ec1da8835fd6fca2368e5ee0ed437d149561def4174d736eb0f0'];
        yield 'pixels gold41' => ['pixels', 'gold41', '9266f00fe758d7879bc6777cf41abb2700f6321eb7ab1f2f0569cd53203e274d'];
        yield 'pixels gold42' => ['pixels', 'gold42', 'b241714a5efc46ab59a46de417a9e25d8aec6f35f3caa6a290bd79b7caeed30c'];
        yield 'pixels gold43' => ['pixels', 'gold43', 'd955e0feb6fdd4e1fd6ed6cc71940748df9528d9ab2e49c5914372cb8d5e4b3c'];
        yield 'pixels gold44' => ['pixels', 'gold44', '3f4ebb7466b0051a1a1406b7c53f74c833436f9da6f204e4b01bae489b8060b0'];
        yield 'pixels gold45' => ['pixels', 'gold45', '0f7a84f8bcf399fb6bd4f5dcc6e21bde357497233bf43dc5a07e8731216ff4d0'];
        yield 'pixels gold46' => ['pixels', 'gold46', '8c774a582fc43814d8cb2a94de79f01cb165e7149ffe93a6689295efbc5da982'];
        yield 'pixels gold47' => ['pixels', 'gold47', 'a1062f3ba217cb77329c412c7baa4e057ccb60b346b26e9b48cf7e68a240f155'];
        yield 'pixels gold48' => ['pixels', 'gold48', 'ac2cea51d273afa73ddcb7489c30d82b02ed8556bb50e64f6ebdfbbc3943064f'];
        yield 'pixels gold49' => ['pixels', 'gold49', '0891318fb874a6fb0b721c117e6c6ca3fd41391dcef458aecfb2079948a625db'];
        yield 'pixels gold50' => ['pixels', 'gold50', 'be4752ead87117ca725e01492fa28926cee6ca28cfae8443cbad059138fd10c4'];
        yield 'pixels gold51' => ['pixels', 'gold51', 'cf7fba7a91889a328b911ec743ae28da92def8822fc2af6c65e1649571209aa1'];
        yield 'pixels gold52' => ['pixels', 'gold52', 'e4437fa402e5c06549be85dc555dee8035e53faf2cf46b249461ef11897fec14'];
        yield 'pixels gold53' => ['pixels', 'gold53', '5409c6043b904cab0758d5c79f2220714bd419cf1ce2c360e7288343f5d3e480'];
        yield 'pixels gold54' => ['pixels', 'gold54', '33d76ac394affe75c26d0d01847904e1f303da496db0f77bba7250cc95c8606d'];
        yield 'pixels gold55' => ['pixels', 'gold55', '7743050e331c1d5df25f96974e8ec767b099b089c558a9187324f8955ed446e5'];
        yield 'pixels gold56' => ['pixels', 'gold56', 'a94e5838c30dbbae5b653b38bf96497d2a2cb0cc048c2578b9c837a96b6cb7c3'];
        yield 'pixels gold57' => ['pixels', 'gold57', '811e9793c2e9a5cff76a33c7df598309047bcad7e4d5918e5254a23b78afa300'];
        yield 'pixels gold58' => ['pixels', 'gold58', '7ef37e7a21c6c748fd10721e8ea35de095e64352051ac3349c4343acb729e88e'];
        yield 'pixels gold59' => ['pixels', 'gold59', '01c4091789c7a6d1faf4be45244799a8f9f5b94f47fc355986e8a1cd344c350f'];
        yield 'pixels gold60' => ['pixels', 'gold60', '3da0fe9420f1b7a61fd64b2461060bade8020751853849dc9d7efef6fbb0a46d'];
    }

    #[DataProvider('drawings')]
    public function testASeedKeepsItsDrawing(string $style, string $seed, string $digest): void
    {
        $canvas = new Canvas(Palette::named('default'), Initials::of('', 2));
        ('shapes' === $style ? new ShapesStyle() : new PixelsStyle())->draw($canvas, new Random($seed));

        self::assertSame($digest, hash('sha256', $canvas->body()));
    }
}
