<?php

/**
 * Test case for "system.cli.commands.MessageCommand"
 * @see MessageCommand
 */
class MessageCommandTest extends CTestCase
{
	protected $sourcePath='';
	protected $messagePath='';
	protected $configFileName='';

	public function setUp(): void
	{
		$this->sourcePath=Yii::getPathOfAlias('application.runtime.test_source');
		$this->createDir($this->sourcePath);
		$this->messagePath=Yii::getPathOfAlias('application.runtime.test_messages');
		$this->createDir($this->messagePath);
		$this->configFileName=Yii::getPathOfAlias('application.runtime').DIRECTORY_SEPARATOR.'message_command_test_config.php';
	}

	public function tearDown(): void
	{
		$this->removeDir($this->sourcePath);
		$this->removeDir($this->messagePath);
		if(file_exists($this->configFileName))
			unlink($this->configFileName);
	}

	/**
	 * Creates directory.
	 * @param $dirName directory full name
	 */
	protected function createDir($dirName)
	{
		if(!file_exists($dirName))
			mkdir($dirName,0777,true);
	}

	/**
	 * Removes directory.
	 * @param $dirName directory full name
	 */
	protected function removeDir($dirName)
	{
		if(!empty($dirName) && file_exists($dirName))
		{
			$this->removeFileSystemObject($dirName);
		}
	}

	/**
	 * Removes file system object: directory or file.
	 * @param string $fileSystemObjectFullName file system object full name.
	 */
	protected function removeFileSystemObject($fileSystemObjectFullName)
	{
		if(!is_dir($fileSystemObjectFullName))
		{
			unlink($fileSystemObjectFullName);
		} else {
			$dirHandle = opendir($fileSystemObjectFullName);
			while(($fileSystemObjectName=readdir($dirHandle))!==false)
			{
				if($fileSystemObjectName==='.' || $fileSystemObjectName==='..')
					continue;
				$this->removeFileSystemObject($fileSystemObjectFullName.DIRECTORY_SEPARATOR.$fileSystemObjectName);
			}
			closedir($dirHandle);
			rmdir($fileSystemObjectFullName);
		}
	}

	/**
	 * @return MessageCommand message command instance
	 */
	protected function createMessageCommand()
	{
		//$command=new MessageCommand('message',null);
		$command=$this->getMockBuilder('MessageCommand')->setMethods(array('usageError'))->setConstructorArgs(array('message',null))->getMock();
		$command->expects($this->any())->method('usageError')->willThrowException(new CException('usageError'));
		return $command;
	}

	/**
	 * Emulates running of the message command.
	 * @param array $args command shell arguments
	 * @return string command output
	 */
	protected function runMessageCommand(array $args)
	{
		$command=$this->createMessageCommand();
		ob_start();
		ob_implicit_flush(false);
		$command->run($args);
		return ob_get_clean();
	}

	/**
	 * Creates message command config file at {@link configFileName}
	 * @param array $config message command config.
	 */
	protected function composeConfigFile(array $config)
	{
		if(file_exists($this->configFileName))
			unlink($this->configFileName);
		$fileContent='<?php return '.var_export($config,true).';';
		file_put_contents($this->configFileName,$fileContent);
	}

	/**
	 * Creates source file with given content
	 * @param string $content file content
	 * @param string|null $name file self name
	 */
	protected function createSourceFile($content,$name=null)
	{
		if(empty($name))
			$name=md5(uniqid()).'.php';
		file_put_contents($this->sourcePath.DIRECTORY_SEPARATOR.$name,$content);
	}

	/**
	 * Creates message file with given messages.
	 * @param string $name file name
	 * @param array $messages messages.
	 */
	protected function createMessageFile($name,array $messages=array())
	{
		$fileName=$this->messagePath.DIRECTORY_SEPARATOR.$name;
		if(file_exists($fileName))
			unlink($fileName);
		else
		{
			$dirName=dirname($fileName);
			if(!file_exists($dirName))
				mkdir($dirName,0777,true);
		}
		$fileContent='<?php return '.var_export($messages,true).';';
		file_put_contents($fileName,$fileContent);
	}

	// Tests:

	public function testEmptyArgs()
	{
		$this->expectException('CException');
		$this->expectExceptionMessage('usageError');
		$this->runMessageCommand(array());
		if (ob_get_level() > 0) {
		    ob_end_clean();
        }
	}

	public function testConfigFileNotExist()
	{
		$this->expectException('CException');
        $this->expectExceptionMessage('usageError');
		$this->runMessageCommand(array('not_existing_file.php'));
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
	}

	public function testCreateTranslation()
	{
		$language = 'en';

		$category='test_category';
		$message='test message';
		$sourceFileContent="Yii::t('{$category}','{$message}')";
		$this->createSourceFile($sourceFileContent);

		$this->composeConfigFile(array(
			'languages'=>array($language),
			'sourcePath'=>$this->sourcePath,
			'messagePath'=>$this->messagePath,
		));
		$this->runMessageCommand(array($this->configFileName));

		$this->assertFileExists($this->messagePath.DIRECTORY_SEPARATOR.$language,'No language dir created!');
		$messageFileName=$this->messagePath.DIRECTORY_SEPARATOR.$language.DIRECTORY_SEPARATOR.$category.'.php';
		$this->assertFileExists($messageFileName,'No message file created!');
		$messages=require($messageFileName);
		$this->assertIsArray($messages, 'Unable to compose messages!');
		$this->assertArrayHasKey($message, $messages, 'Source message is missing!');
	}

	/**
	 * @depends testCreateTranslation
	 */
	public function testNothingNew()
	{
		$language = 'en';

		$category='test_category1';
		$message='test message';
		$sourceFileContent = "Yii::t('{$category}','{$message}')";
		$this->createSourceFile($sourceFileContent);

		$this->composeConfigFile(array(
			'languages'=>array($language),
			'sourcePath'=>$this->sourcePath,
			'messagePath'=>$this->messagePath,
		));
		$this->runMessageCommand(array($this->configFileName));

		$messageFileName=$this->messagePath.DIRECTORY_SEPARATOR.$language.DIRECTORY_SEPARATOR.$category.'.php';

		// check file not overwritten:
		$messageFileContent=file_get_contents($messageFileName);
		$messageFileContent.='// some not generated by command content';
		file_put_contents($messageFileName,$messageFileContent);

		$this->runMessageCommand(array($this->configFileName));

		$this->assertEquals($messageFileContent,file_get_contents($messageFileName));
	}

	/**
	 * @depends testCreateTranslation
	 */
	public function testMerge()
	{
		if (defined('HHVM_VERSION')) {
			$this->markTestSkipped('This test needs duplicate require of a file after its has changed, this does not work on HHVM.');
		}

		$language = 'en';
		$category='test_category2';
		$messageFileName=$language.DIRECTORY_SEPARATOR.$category.'.php';

		$existingMessage='test existing message';
		$existingMessageContent='test existing message content';
		$this->createMessageFile($messageFileName,array(
			$existingMessage=>$existingMessageContent
		));

		$newMessage='test new message';
		$sourceFileContent = "Yii::t('{$category}','{$existingMessage}')";
		$sourceFileContent .= "Yii::t('{$category}','{$newMessage}')";
		$this->createSourceFile($sourceFileContent);

		$this->composeConfigFile(array(
			'languages'=>array($language),
			'sourcePath'=>$this->sourcePath,
			'messagePath'=>$this->messagePath,
			'overwrite'=>true,
		));
		$this->runMessageCommand(array($this->configFileName));

		$messages=require($this->messagePath.DIRECTORY_SEPARATOR.$messageFileName);
		$this->assertArrayHasKey($newMessage, $messages, 'Unable to add new message!');
		$this->assertArrayHasKey($existingMessage, $messages, 'Unable to keep existing message!');
		$this->assertEquals('',$messages[$newMessage],'Wrong new message content!');
		$this->assertEquals($existingMessageContent,$messages[$existingMessage],'Unable to keep existing message content!');
	}

	/**
	 * @depends testMerge
	 */
	public function testNoLongerNeedTranslation()
	{
		if (defined('HHVM_VERSION')) {
			$this->markTestSkipped('This test needs duplicate require of a file after its has changed, this does not work on HHVM.');
		}

		$language = 'en';
		$category='test_category3';
		$messageFileName=$language.DIRECTORY_SEPARATOR.$category.'.php';

		$oldMessage='test old message';
		$oldMessageContent='test old message content';
		$this->createMessageFile($messageFileName,array(
			$oldMessage=>$oldMessageContent
		));

		$sourceFileContent = "Yii::t('{$category}','some new message')";
		$this->createSourceFile($sourceFileContent);

		$this->composeConfigFile(array(
			'languages'=>array($language),
			'sourcePath'=>$this->sourcePath,
			'messagePath'=>$this->messagePath,
			'overwrite'=>true,
			'removeOld'=>false,
		));
		$this->runMessageCommand(array($this->configFileName));

		$messages=require($this->messagePath.DIRECTORY_SEPARATOR.$messageFileName);

		$this->assertArrayHasKey($oldMessage, $messages, 'No longer needed message removed!');
		$this->assertEquals('@@'.$oldMessageContent.'@@',$messages[$oldMessage],'No longer needed message content does not marked properly!');
	}

	/**
	 * @depends testMerge
	 * @see https://github.com/yiisoft/yii/issues/2244
	 */
	public function testMergeWithContentZero()
	{
		if (defined('HHVM_VERSION')) {
			$this->markTestSkipped('This test needs duplicate require of a file after its has changed, this does not work on HHVM.');
		}

		$language = 'en';
		$category='test_category4';
		$messageFileName=$language.DIRECTORY_SEPARATOR.$category.'.php';

		$zeroMessage='test zero message';
		$zeroMessageContent='0';
		$falseMessage='test false message';
		$falseMessageContent='false';
		$this->createMessageFile($messageFileName,array(
			$zeroMessage=>$zeroMessageContent,
			$falseMessage=>$falseMessageContent,
		));

		$newMessage='test new message';
		$sourceFileContent = "Yii::t('{$category}','{$zeroMessage}')";
		$sourceFileContent .= "Yii::t('{$category}','{$falseMessage}')";
		$sourceFileContent .= "Yii::t('{$category}','{$newMessage}')";
		$this->createSourceFile($sourceFileContent);

		$this->composeConfigFile(array(
			'languages'=>array($language),
			'sourcePath'=>$this->sourcePath,
			'messagePath'=>$this->messagePath,
			'overwrite'=>true,
		));
		$this->runMessageCommand(array($this->configFileName));

		$messages=require($this->messagePath.DIRECTORY_SEPARATOR.$messageFileName);
		$this->assertSame($messages[$zeroMessage], $zeroMessageContent, 'Message content "0" is lost!');
		$this->assertSame($messages[$falseMessage], $falseMessageContent, 'Message content "false" is lost!');
	}

	/**
	 * @depends testCreateTranslation
	 * @see https://github.com/yiisoft/yii/issues/1228
	 */
	public function testMultiplyTranslators()
	{
		if (defined('HHVM_VERSION')) {
			$this->markTestSkipped('This test needs duplicate require of a file after its has changed, this does not work on HHVM.');
		}

		$language = 'en';
		$category='test_category5';

		$translators=array(
			'Yii::t',
			'Custom::translate',
		);

		$sourceMessages=array(
			'first message',
			'second message',
		);
		$sourceFileContent='';
		foreach($sourceMessages as $key => $message)
			$sourceFileContent.=$translators[$key]."('{$category}','{$message}');\n";
		$this->createSourceFile($sourceFileContent);

		$this->composeConfigFile(array(
			'languages'=>array($language),
			'sourcePath'=>$this->sourcePath,
			'messagePath'=>$this->messagePath,
			'translator'=>$translators,
		));
		$this->runMessageCommand(array($this->configFileName));

		$messageFileName=$this->messagePath.DIRECTORY_SEPARATOR.$language.DIRECTORY_SEPARATOR.$category.'.php';
		$messages=require($messageFileName);

		foreach($sourceMessages as $sourceMessage)
			$this->assertArrayHasKey($sourceMessage, $messages);
	}
}
