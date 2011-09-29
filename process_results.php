<?php

	error_reporting(0);

	$args = $argv[1];
	$file = $argv[2];
	$cwd = $argv[3];
	$optional = $argv[4];

	if ($args == "-v") {
		$verbose = 1;
	}

	if (substr($optional,0,1) == "/") {
		$output_dir = $optional;
	} else {
		$output_dir = $cwd . "/" . $optional;
	}

	$cache_path = substr($file,0,strrpos($file,"/"));
	chdir($cache_path);
	@unlink("csv");
	mkdir("csv");	

	process_results($file);
	process_cv2();
	normalise_all($cache_path."/csv");
	exif_wrapper($cache_path."/csv");
	output_results($cache_path."/csv",$output_dir);

	function output_results($in_path,$out_path) {
		@mkdir($out_path);
		$handle = opendir($in_path);
		while (false !== ($file = readdir($handle))) {
			if (strpos($file,".csv") !== false) {
				$old_path = $in_path . "/" . $file;
				$new_path = $out_path . "/" . $file;
				copy($old_path,$new_path);
			}
		}
	}
	
	function process_results($file) {

		$complex[] = "DOS EPS Binary File";
		$complex[] = "Targa image data";
		$complex[] = "DBase 3 data file";
		$complex[] = "StuffIt Archive";	
		$complex[] = "PostScript document";
		$complex[] = "PE32 executable";
		$complex[] = "cannot open";
		$complex[] = "Microsoft_Outlook_email_folder";
		$complex[] = "Macromedia_Flash_data";
		$complex[] = "Apple_QuickTime_movie";

		$handle = fopen($file,'r');
		while ($line = fgets($handle,4096)) {
			$parts = explode(":",$line);
			$file_name = trim($parts[0]);
			$rest = trim($parts[1]);
			$types = explode(",",$rest,2);
			$type = trim($types[0]);
			if (count($parts) > 2 && $type != "CDF V2 Document") {
				$key_pairs[$type]++;
				$parts2 = explode(":",$line,2);
				$line = $parts2[0] . ": " . str_replace(":"," ",$parts2[1]);
			}
			if ($type != "CDF V2 Document") {
				$rep_count = 1;
				$line = str_replace(":",",",$line,$rep_count);
			}

			for ($i=0; $i<count($complex);$i++) {
				if (substr($type,0,strlen($complex[$i])) == $complex[$i]) {
					$type = $complex[$i];
				}
			}

			$out[$type]++;	
			$lines[$type][] = $line;
		}

		arsort($out);

		fclose($handle);

		$file = 'csv/overview.csv';
		$handle = fopen($file,"w");
		fwrite($handle,"Type, Count\n");
		foreach ($out as $type => $count) {
			fwrite($handle,"$type, $count\n");
		}
		fclose($handle);

		foreach ($out as $type => $count) {
			$file_type = str_replace("("," ",$type);
			$file_type = str_replace(")"," ",$file_type);
			$file_type = trim($file_type);
			$file_type = str_replace(" ","_",$file_type);
			$file_type = str_replace("/","_",$file_type);
			$file = "csv/" . $file_type . ".csv";
			$handle = fopen($file,"w");
			$lines_array = $lines[$type];
			for($i=0;$i<count($lines_array);$i++) {
				@fwrite($handle,trim($lines_array[$i]) . "\n");
			}
			fclose($handle);
		}

	}

	function process_cv2() {

		if (!file_exists("csv/CDF_V2_Document.csv")) {
			return;
		}
	
		$keys = "";
		$keys["File Name"] = "File Name";
		$keys["File Type"] = "File Type";
		$keys["Os"] = "Os";
		$keys["Code page"] = "Code page";
		$keys["Title"] = "Title";
		$keys["Author"] = "Author";
		$keys["Template"] = "Template";
		$keys["Last Saved By"] = "Last Saved By";
		$keys["Revision Number"] = "Revision Number";
		$keys["Name of Creating Application"] = "Name of Creating Application";
		$keys["Last Printed"] = "Last Printed";
		$keys["Create Time/Date"] = "Create Time/Date";
		$keys["Last Saved Time/Date"] = "Last Saved Time/Date";
		$keys["Number of Pages"] = "Number of Pages";
		$keys["Number of Words"] = "Number of Words";
		$keys["Number of Characters"] = "Number of Characters";
		$keys["Security"] = "Security";
		$keys["Total Editing Time"] = "Total Editing Time";
		$keys["Version"] = "Version";
		$keys["Comments"] = "Comments";
		$keys["Keywords"] = "Keywords";
		$keys["Subject"] = "Subject";

		$file = "csv/CDF_V2_Document.csv";
		$file2 = "csv/CDF_V2_Document_out.csv";

		$handle = fopen($file,"r");
		$handle2 = fopen($file2,"w");

		foreach ($keys as $key => $foo) {
			fwrite($handle2,$key . ", ");
		}
		fwrite($handle2,"\n");

		while ($line = fgets($handle,4096)) {
			$data = "";
			$parts = explode(": ",$line);
			$file_path = trim($parts[0]);
			fwrite($handle2,$file_path);
			$current_key = "File Type";
			for($i=1;$i<count($parts)-1;$i++) {
				$data_bits = explode(",",$parts[$i]);
				for ($j=0;$j<count($data_bits)-1;$j++) {
					$data[$file_path][$current_key] .= $data_bits[$j] . ', ';
				}
				$data[trim($current_key)] = trim(substr($data[$file_path][$current_key],0,-2));
				$next_key = trim($data_bits[count($data_bits)-1]);
				if ($keys[$next_key]) {
					$current_key = $data_bits[count($data_bits)-1];
				} else {
					$data[$current_key] .= $next_key;
				}
			}
			foreach ($keys as $key => $foo) {
				if ($data[$key]) {
					fwrite($handle2,str_replace(","," ",$data[$key]) . ", ");
				} else {
					fwrite($handle2,", ");
				}
			}
			fwrite($handle2,"\n");
		}
		fclose($handle);
		fclose($handle2);

	}

	function normalise_all($path) {
		$handle = opendir($path);
		while (false !== ($file = readdir($handle))) {
			if (strpos($file,".csv") !== false) {
				$new_path = $path . '/' . $file;
				normalise_file($new_path);
			}
		}
	}

	function normalise_file($new_path) {
		global $verbose;
		if ($verbose) {
			echo "Normalising $new_path \n"; 
		}

		$max_count = 0;

		$handle = fopen($new_path,"r");
		while ($line = fgets($handle,4096)) {
			$count = count(explode(",",$line));
			if ($count > $max_count) {
				$max_count = $count;
			}
		}
		fclose($handle);

		$cache_file = $new_path . ".tmp";
		
		$handle = fopen($new_path,"r");
		$write_handle = fopen($cache_file,"w");
	
		$line_count = 0;	
		while ($line = fgets($handle,4096)) {
			$line = trim($line);
			if ($line_count < 1) {
				if (substr($line,0,strlen("File Path")) != "File Path" && substr($line,0,strlen("Type")) != "Type") {
					$old_line = $line;
					$line = "File Path, ";
					$count = 1;
					while ($count < $max_count) {
						$line = $line . ", ";
						$count++;
					}
					fwrite($write_handle,$line . "\n");
					$line = $old_line;
				}
				$line_count++;
			}
			$count = count(explode(",",$line));
			while ($count < $max_count) {
				$line = $line . ", ";
				$count++;
			}
			fwrite($write_handle,$line . "\n");
		}
		fclose($handle);
		fclose($write_handle);
		copy($cache_file,$new_path);
		unlink($cache_file);
	}

	function combine_files($file1, $file2) {
		global $verbose;
		if ($verbose) {
			echo "Combining $file1 and $file2 \n";	
		}

		$handle = fopen($file2,"r");
		$keys = fgets($handle,4096);
		$parts = explode(",",$keys,2);
		$keys = $parts[1];
		fclose($handle);

		$write_file = $file1 . ".tmp";
		$write_handle = fopen($write_file,"w");

		$handle = fopen($file1,"r");
		$count = 0;
		while ($line = fgets($handle,4096)) {
			if ($count < 1) {
				fwrite($write_handle,trim($line) . ' ' . trim($keys) . "\n");
				$count++;
			} else {
				$parts = explode(",",$line);
				$file_path = $parts[0];
				$ret = exec('grep "' . $file_path . '" ' . $file2);
				$parts = explode(",",$ret,2);
				$ret = $parts[1];
				$output = trim($line) . ' ' . trim($ret) . "\n";
				fwrite($write_handle,$output);
			}
		}
		fclose($handle);
		fclose($write_handle);
		copy($write_file,$file1);
		unlink($write_file);
		unlink($file2);
	}


	function exif_wrapper($path) {
		global $verbose;

		$cache_file = 'image_cache.tmp';

		$handle = opendir($path);
		while (false !== ($file = readdir($handle))) {
			if (strpos(strtolower($file),"pdf") !== false) {
				$to_process = $path . "/" . $file;
				if ($verbose) { echo "Processing $to_process\n"; }
				exif_process($to_process);	
			}
			if (strpos(strtolower($file),"image_data") !== false) {
				$to_process = $path . "/" . $file;
				if ($verbose) { echo "Processing $to_process\n"; }
				exif_process($to_process);	
			}
			if (strpos(strtolower($file),"pc_bitmap") !== false) {
				$to_process = $path . "/" . $file;
				if ($verbose) { echo "Processing $to_process\n"; }
				exif_process($to_process);	
			}
		}
		fclose($handle);
	}

	function exif_process($file_list) {
		if ($file_list == 'csv/GIF_image_data.csv') {
			return;
		}
		global $all_keys;
		$cache_file = $file_list . ".cache";
		$cache_file2 = $file_list . ".cache2";
		@unlink($cache_file);
		$write_handle = fopen($cache_file,"w");

		$handle = fopen($file_list,"r");
		while ($line = fgets($handle,4096)) {
			$parts = explode(",",$line);
			$file_path = trim($parts[0]);
			fwrite($write_handle,$file_path);
			$data = exec('exiftool "'.$file_path.'" 2>/dev/null',$ret);
			for ($i=0;$i<count($ret);$i++) {
				$line = $ret[$i];
				$parts = explode("  : ",$line);
				
				$key = trim($parts[0]);
				$value = trim($parts[1]);

				$all_keys[$key] = $key;
				fwrite($write_handle,"-=-=" . $key . "  :  " . $value);
				
			}
			fwrite($write_handle,"\n");
		}

		fclose($handle);
		fclose($write_handle);
		
		$handle = fopen($cache_file,"r");
		$write_handle = fopen($cache_file2,"w");
		fwrite($write_handle,"File Path, ");
		foreach ($all_keys as $key => $foo) {
			fwrite($write_handle,$key . ", ");
		}
		fwrite($write_handle,"\n");

		while ($line = fgets($handle,4096)) {
			$data = "";
			$parts = explode("-=-=",$line);
			$file_path = $parts[0];
			fwrite($write_handle,$file_path . ", ");
			for($i=1;$i<count($parts);$i++) {
				$pairs = explode(" : ",$parts[$i]);
				$key = trim($pairs[0]);
				$value = trim($pairs[1]);
				$data[$key] = $value;
			}
			foreach ($all_keys as $key => $foo) {
				fwrite($write_handle,", " . $data[$key]);
			}
			fwrite($write_handle,"\n");
		}
		
		fclose($handle);
		fclose($write_handle);
	
		unlink($cache_file);

		normalise_file($cache_file2);

		combine_files($file_list,$cache_file2);	
	
	}

?>
