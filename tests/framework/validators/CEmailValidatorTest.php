<?php
require_once('ValidatorTestModel.php');

class CEmailValidatorTest extends CTestCase
{
	public function testEmpty()
	{
		$emailValidator = new CEmailValidator();
		$emailValidator->allowEmpty = true;
		$this->assertTrue($emailValidator->validateValue('test@example.com'));
		$this->assertFalse($emailValidator->validateValue(''));
		
		$emailValidator->allowEmpty = false;
		$this->assertTrue($emailValidator->validateValue('test@example.com'));
		$this->assertFalse($emailValidator->validateValue(''));
	}

	public function testNumericEmail()
	{
		$emailValidator = new CEmailValidator();
		$result = $emailValidator->validateValue("5011@gmail.com");
		$this->assertTrue($result);
	}

	public function providerIDNEmail()
	{
		return array(
			// IDN validation enabled
			array('test@президент.рф', true, true),
			array('test@bücher.de', true, true),
			array('test@检查域.cn', true, true),
			array('☃-⌘@mañana.com', true, false),
			array('test@google.com', true, true),
			array('test@yiiframework.com', true, true),
			array('bad-email', true, false),
			array('without@tld', true, false),
			array('without.at-mark.com', true, false),
			array('检查域', true, false),

			// IDN validation disabled
			array('test@президент.рф', false, false),
			array('test@bücher.de', false, false),
			array('test@检查域.cn', false, false),
			array('☃-⌘@mañana.com', false, false),
			array('test@google.com', false, true),
			array('test@yiiframework.com', false, true),
			array('bad-email', false, false),
			array('without@tld', false, false),
			array('without.at-mark.com', false, false),
			array('检查域', false, false),
		);
	}

	/**
	 * @dataProvider providerIDNEmail
	 *
	 * @param string $email
	 * @param bool $validateIDN
	 * @param string $assertion
	 */
	public function testIDNUrl($email, $validateIDN, $assertion)
	{
		$emailValidator = new CEmailValidator();
		$emailValidator->validateIDN = $validateIDN;
		$result = $emailValidator->validateValue($email);
		$this->assertEquals($assertion, $result);
	}

	/**
	 * https://github.com/yiisoft/yii/issues/1955
	 */
	public function testArrayValue()
	{
		$model=new ValidatorTestModel('CEmailValidatorTest');
		$model->email=array('user@domain.tld');
		$model->validate(array('email'));
		$this->assertTrue($model->hasErrors('email'));
		$this->assertEquals(array('Email is not a valid email address.'),$model->getErrors('email'));
	}

	public function testMxPortDomainWithNoMXRecord()
	{
		if (getenv('TRAVIS')==='true' || getenv('GITHUB_ACTIONS')==='true')
			$this->markTestSkipped('MX connections are disabled in travis.');

		$emailValidator = new CEmailValidator();
		$emailValidator->checkPort = true;
		$result = $emailValidator->validateValue('user@example.com');
		$this->assertFalse($result);
	}

	public function testMxPortDomainWithMXRecord()
	{
		if (getenv('TRAVIS')==='true')
			$this->markTestSkipped('MX connections are disabled in travis.');

		$emailValidator = new CEmailValidator();
		$emailValidator->checkPort = true;
		$result = $emailValidator->validateValue('user@hotmail.com');
		$this->assertTrue($result);
	}
}
