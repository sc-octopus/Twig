<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once(dirname(__FILE__).'/../lib/lime/LimeAutoloader.php');
LimeAutoloader::register();

require_once dirname(__FILE__).'/../../lib/Twig/Autoloader.php';
Twig_Autoloader::register();

class Foo
{
  public function bar($param1 = null, $param2 = null)
  {
    return 'bar'.($param1 ? '_'.$param1 : '').($param2 ? '-'.$param2 : '');
  }

  public function getFoo()
  {
    return 'foo';
  }

  public function getSelf()
  {
    return $this;
  }
}

class TestExtension extends Twig_Extension
{
  public function getFilters()
  {
    return array('nl2br' => new Twig_Filter_Method($this, 'nl2br'));
  }

  public function nl2br($value, $sep = '<br />')
  {
    return str_replace("\n", $sep."\n", $value);
  }

  public function getName()
  {
    return 'test';
  }
}

$t = new LimeTest(59);
$fixturesDir = realpath(dirname(__FILE__).'/../fixtures/');

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixturesDir), RecursiveIteratorIterator::LEAVES_ONLY) as $file)
{
  if (!preg_match('/\.test$/', $file))
  {
    continue;
  }

  $test = file_get_contents($file->getRealpath());

  if (!preg_match('/--TEST--\s*(.*?)\s*((?:--TEMPLATE(?:\(.*?\))?--(?:.*?))+)--DATA--.*?--EXPECT--.*/s', $test, $match))
  {
    throw new InvalidArgumentException(sprintf('Test "%s" is not valid.', str_replace($fixturesDir.'/', '', $file)));
  }

  $message = $match[1];
  $templates = array();
  preg_match_all('/--TEMPLATE(?:\((.*?)\))?--(.*?)(?=\-\-TEMPLATE|$)/s', $match[2], $matches, PREG_SET_ORDER);
  foreach ($matches as $match)
  {
    $templates[($match[1] ? $match[1] : 'index.twig')] = $match[2];
  }

  $loader = new Twig_Loader_Array($templates);
  $twig = new Twig_Environment($loader, array('trim_blocks' => true, 'cache' => false));
  $twig->addExtension(new Twig_Extension_Escaper());
  $twig->addExtension(new TestExtension());

  $template = $twig->loadTemplate('index.twig');

  preg_match_all('/--DATA--(.*?)--EXPECT--(.*?)(?=\-\-DATA\-\-|$)/s', $test, $matches, PREG_SET_ORDER);
  foreach ($matches as $match)
  {
    $output = trim($template->render(eval($match[1].';')), "\n ");
    $expected = trim($match[2], "\n ");

    $t->is($output, $expected, $message);
    if ($output != $expected)
    {
      $t->comment('Compiled template that failed:');

      foreach (array_keys($templates) as $name)
      {
        list($source, ) = $loader->getSource($name);
        $t->comment($twig->compile($twig->parse($twig->tokenize($source, $name))));
      }
    }
  }
}
