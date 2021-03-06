<?php
class CFileHelperTest extends CTestCase
{
	private $testDir;
	private $testMode=0770;
	private $rootDir1="test1";
	private $rootDir2="test2";
	private $subDir='sub';
	private $file1='testfile';
	private $file2='.htaccess';
	private $file3='..svn';
	private $file4='non-existent-file';

	protected function setUp(): void
	{
		$this->testDir=Yii::getPathOfAlias('application.runtime.CFileHelper');
		if(!is_dir($this->testDir) && !(@mkdir($this->testDir)))
			$this->markTestIncomplete('Unit tests runtime directory should have writable permissions!');

		// create temporary testing data files
		$filesData=array(
			'mimeTypes1.php'=>"<?php return array('txa'=>'application/json','txb'=>'another/mime');",
			'mimeTypes2.php'=>"<?php return array('txt'=>'text/plain','txb'=>'another/mime2');",
		);
		foreach($filesData as $fileName=>$fileData)
			if(!(@file_put_contents($this->testDir.$fileName,$fileData)))
				$this->markTestIncomplete('Unit tests runtime directory should have writable permissions!');
	}

	protected function tearDown(): void
	{
		if (is_dir($this->testDir))
			$this->rrmdir($this->testDir);
	}

	public function testGetMimeTypeByExtension()
	{
		// run everything ten times in one test action to be sure that caching inside
		// CFileHelper::getMimeTypeByExtension() is working the right way
		for($i=0;$i<10;$i++)
		{
			$this->assertNull(CFileHelper::getMimeTypeByExtension('test.txa'));
			$this->assertNull(CFileHelper::getMimeTypeByExtension('test.txb'));
			$this->assertEquals('text/plain',CFileHelper::getMimeTypeByExtension('test.txt'));

			$this->assertEquals('application/json',CFileHelper::getMimeTypeByExtension('test.txa',$this->testDir.'mimeTypes1.php'));
			$this->assertEquals('another/mime',CFileHelper::getMimeTypeByExtension('test.txb',$this->testDir.'mimeTypes1.php'));
			$this->assertNull(CFileHelper::getMimeTypeByExtension('test.txt',$this->testDir.'mimeTypes1.php'));

			$this->assertNull(CFileHelper::getMimeTypeByExtension('test.txa',$this->testDir.'mimeTypes2.php'));
			$this->assertEquals('another/mime2',CFileHelper::getMimeTypeByExtension('test.txb',$this->testDir.'mimeTypes2.php'));
			$this->assertEquals('text/plain',CFileHelper::getMimeTypeByExtension('test.txt',$this->testDir.'mimeTypes2.php'));
		}
	}

	public function testCopyDirectory_subDir_modeShoudBe0775()
	{
		if (substr(PHP_OS,0,3)=='WIN')
			$this->markTestSkipped("Can't reliably test it on Windows because fileperms() always return 0777.");

		$this->createTestStruct($this->testDir);
		$src=$this->testDir.DIRECTORY_SEPARATOR.$this->rootDir1;
		$dst=$this->testDir.DIRECTORY_SEPARATOR.$this->rootDir2;
		CFileHelper::copyDirectory($src,$dst,array('newDirMode'=>$this->testMode));

		$subDir2Mode=$this->getMode($dst.DIRECTORY_SEPARATOR.$this->subDir);
		$expectedMode= '0770';
		$this->assertEquals($expectedMode,$subDir2Mode,"Subdir mode is not {$expectedMode}");
	}

	public function testCopyDirectory_subDir_modeShoudBe0777()
	{
		if (substr(PHP_OS,0,3)=='WIN')
			$this->markTestSkipped("Can't reliably test it on Windows because fileperms() always return 0777.");

		$this->createTestStruct($this->testDir);
		$src=$this->testDir.DIRECTORY_SEPARATOR.$this->rootDir1;
		$dst=$this->testDir.DIRECTORY_SEPARATOR.$this->rootDir2;
		CFileHelper::copyDirectory($src,$dst);

		$subDir2Mode=$this->getMode($dst.DIRECTORY_SEPARATOR.$this->subDir);
		$expectedMode= '0777';
		$this->assertEquals($expectedMode,$subDir2Mode,"Subdir mode is not {$expectedMode}");
	}

	public function testRemoveDirectory()
	{
		$this->createTestStruct($this->testDir);

		$ds=DIRECTORY_SEPARATOR;
		$bd=$this->testDir.$ds;

		$this->assertDirectoryExists($bd.$this->rootDir1);
		$this->assertDirectoryExists($bd.$this->rootDir1.$ds.$this->subDir);
		$this->assertDirectoryNotExists($bd.$this->rootDir2);
		$this->assertTrue(is_file($bd.$this->rootDir1.$ds.$this->subDir.$ds.$this->file1));
		$this->assertTrue(is_file($bd.$this->rootDir1.$ds.$this->subDir.$ds.$this->file2));
		$this->assertTrue(is_file($bd.$this->rootDir1.$ds.$this->subDir.$ds.$this->file3));
		$this->assertFalse(is_file($bd.$this->rootDir1.$ds.$this->subDir.$ds.$this->file4));

		CFileHelper::removeDirectory($bd.$this->rootDir2);

		$this->assertDirectoryExists($bd.$this->rootDir1);
		$this->assertDirectoryExists($bd.$this->rootDir1.$ds.$this->subDir);
		$this->assertDirectoryNotExists($bd.$this->rootDir2);
		$this->assertTrue(is_file($bd.$this->rootDir1.$ds.$this->subDir.$ds.$this->file1));
		$this->assertTrue(is_file($bd.$this->rootDir1.$ds.$this->subDir.$ds.$this->file2));
		$this->assertTrue(is_file($bd.$this->rootDir1.$ds.$this->subDir.$ds.$this->file3));
		$this->assertFalse(is_file($bd.$this->rootDir1.$ds.$this->subDir.$ds.$this->file4));

		CFileHelper::removeDirectory($bd);

		$this->assertDirectoryNotExists($bd.$this->rootDir1);
		$this->assertDirectoryNotExists($bd.$this->rootDir1.$ds.$this->subDir);
		$this->assertDirectoryNotExists($bd.$this->rootDir2);
		$this->assertFalse(is_file($bd.$this->rootDir1.$ds.$this->subDir.$ds.$this->file1));
		$this->assertFalse(is_file($bd.$this->rootDir1.$ds.$this->subDir.$ds.$this->file2));
		$this->assertFalse(is_file($bd.$this->rootDir1.$ds.$this->subDir.$ds.$this->file3));
		$this->assertFalse(is_file($bd.$this->rootDir1.$ds.$this->subDir.$ds.$this->file4));
	}

	public function testRemoveDirectorySymlinks1()
	{
		if(strtolower(substr(PHP_OS,0,3))=='win')
			$this->markTestSkipped('Cannot test this on MS Windows since symlinks are uncommon for it.');

		$ds=DIRECTORY_SEPARATOR;
		$td=$this->testDir.$ds;

		$this->createSymlinkedDirectoriesAndFiles();
		CFileHelper::removeDirectory($td.'symlinks');

		$this->assertDirectoryNotExists($td.'symlinks');

		$this->assertTrue(is_file($td.'file'));
		$this->assertFalse(is_link($td.'symlinks'.$ds.'symlink-file'));

		$this->assertDirectoryExists($td.'directory');
		$this->assertTrue(is_file($td.'directory'.$ds.'directory-file')); // file inside symlinked dir was left as is
		$this->assertFalse(is_link($td.'symlinks'.$ds.'symlink-directory'));
		$this->assertFalse(is_file($td.'symlinks'.$ds.'symlink-directory'.$ds.'directory-file'));
	}

	public function testRemoveDirectorySymlinks2()
	{
		if(strtolower(substr(PHP_OS,0,3))=='win')
			$this->markTestSkipped('Cannot test this on MS Windows since symlinks are uncommon for it.');

		$ds=DIRECTORY_SEPARATOR;
		$td=$this->testDir.$ds;

		$this->createSymlinkedDirectoriesAndFiles();
		CFileHelper::removeDirectory($td.'symlinks',array('traverseSymlinks'=>true));

		$this->assertDirectoryNotExists($td.'symlinks');

		$this->assertTrue(is_file($td.'file'));
		$this->assertFalse(is_link($td.'symlinks'.$ds.'symlink-file'));

		$this->assertDirectoryExists($td.'directory');
		$this->assertFalse(is_file($td.'directory'.$ds.'directory-file')); // file inside symlinked dir was deleted
		$this->assertFalse(is_link($td.'symlinks'.$ds.'symlink-directory'));
		$this->assertFalse(is_file($td.'symlinks'.$ds.'symlink-directory'.$ds.'directory-file'));
	}

	public function testFindFiles_absolutePaths()
	{
		$this->createTestStruct($this->testDir);

		$bd=$this->testDir.DIRECTORY_SEPARATOR.$this->rootDir1.DIRECTORY_SEPARATOR;

		$files=CFileHelper::findFiles($this->testDir);

		$this->assertEquals($bd.'sub'.DIRECTORY_SEPARATOR.'..svn',$files[0]);
		$this->assertEquals($bd.'sub'.DIRECTORY_SEPARATOR.'.htaccess',$files[1]);
		$this->assertEquals($bd.'sub'.DIRECTORY_SEPARATOR.'testfile',$files[2]);
	}

	public function testFindFiles_relativePaths()
	{
		$this->createTestStruct($this->testDir);

		$bd=$this->rootDir1.DIRECTORY_SEPARATOR;

		$files=CFileHelper::findFiles($this->testDir,array('absolutePaths'=>0));

		$this->assertEquals($bd.'sub'.DIRECTORY_SEPARATOR.'..svn',$files[0]);
		$this->assertEquals($bd.'sub'.DIRECTORY_SEPARATOR.'.htaccess',$files[1]);
		$this->assertEquals($bd.'sub'.DIRECTORY_SEPARATOR.'testfile',$files[2]);
	}

	public function testCreateDirectory()
	{
		$path = $this->testDir . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'path';
		$this->assertTrue(CFileHelper::createDirectory($path,null,true));
		$this->assertDirectoryExists($path);
	}

	private function createSymlinkedDirectoriesAndFiles()
	{
		$ds=DIRECTORY_SEPARATOR;
		$td=$this->testDir.$ds;

		mkdir($td.'symlinks');

		touch($td.'file');
		symlink($td.'file',$td.'symlinks'.$ds.'symlink-file');

		mkdir($td.'directory');
		touch($td.'directory'.$ds.'directory-file');
		symlink($td.'directory',$td.'symlinks'.$ds.'symlink-directory');

		$this->assertDirectoryExists($td.'symlinks');

		$this->assertTrue(is_file($td.'file'));
		$this->assertTrue(is_link($td.'symlinks'.$ds.'symlink-file'));

		$this->assertDirectoryExists($td.'directory');
		$this->assertTrue(is_file($td.'directory'.$ds.'directory-file'));
		$this->assertTrue(is_link($td.'symlinks'.$ds.'symlink-directory'));
		$this->assertTrue(is_file($td.'symlinks'.$ds.'symlink-directory'.$ds.'directory-file'));
	}

	private function createTestStruct($testDir)
	{
		$rootDir=$testDir.DIRECTORY_SEPARATOR.$this->rootDir1;
		mkdir($rootDir);

		$subDir=$testDir.DIRECTORY_SEPARATOR.$this->rootDir1.DIRECTORY_SEPARATOR.$this->subDir;
		mkdir($subDir);

		$file1=$testDir.DIRECTORY_SEPARATOR.$this->rootDir1.DIRECTORY_SEPARATOR.$this->subDir.DIRECTORY_SEPARATOR.$this->file1;
		file_put_contents($file1,'12321312');

		$file2=$testDir.DIRECTORY_SEPARATOR.$this->rootDir1.DIRECTORY_SEPARATOR.$this->subDir.DIRECTORY_SEPARATOR.$this->file2;
		file_put_contents($file2,'.htaccess');

		$file3=$testDir.DIRECTORY_SEPARATOR.$this->rootDir1.DIRECTORY_SEPARATOR.$this->subDir.DIRECTORY_SEPARATOR.$this->file3;
		file_put_contents($file3,'..svn');
	}

	private function getMode($file)
	{
		return substr(sprintf('%o',fileperms($file)),-4);
	}

	private function rrmdir($dir)
	{
		if($handle=opendir($dir))
		{
			while(false!==($entry=readdir($handle)))
			{
				if($entry!="." && $entry!="..")
				{
					if(is_dir($dir."/".$entry)===true)
						$this->rrmdir($dir."/".$entry);
					else
						unlink($dir."/".$entry);
				}
			}
			closedir($handle);
			rmdir($dir);
		}
	}
}
