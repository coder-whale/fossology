<?php
/***********************************************************
 Copyright (C) 2008-2012 Hewlett-Packard Development Company, L.P.

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 version 2 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 ***********************************************************/
/**
 * \file common-job.php
 * \brief library of functions used by the ui to manage jobs.
 *        Jobs information is stored in the jobs, jobdepends,
 *        and jobqueue tables.
 * 
 * Terminology:
 * Scheduled jobs are divided into a specific heirarchy.
 * 
 * "Job"
 * This is the Job container and is saved in a database
 * job record.  
 *
 * "JobQueue"
 * There may be several tasks to perform for a job.  
 * For example, a job may be composed of
 * an unpack task, an adj2nest task, and a nomos task.
 * Each job task is specified in a database jobqueue record.
 * 
 * JobQueue tasks may have dependencies upon the completion of
 * other JobQueue tasks.  The jobdepends tables keep those
 * parent child relationships.
 * 
 **/


/**
 * \brief Insert a new upload record, and update the foldercontents table.
 *
 * \param $user_pk
 * \param $job_name   Job name
 * \param $filename   For upload from URL, this is the URL.\n
 *                    For upload from file, this is the filename.\n
 *                    For upload from server, this is the file path.\n
 * \param $desc       Optional user file description.
 * \param $UploadMode 1<<2=URL, 1<<3=upload from server or file
 * \param $folder_pk   The folder to contain this upload
 *
 * \return upload_pk or null (failure)
 */
function JobAddUpload($user_pk, $job_name, $filename, $desc, $UploadMode, $folder_pk) 
{
  global $PG_CONN;

  /* check all required inputs */
  if (empty($user_pk) or empty($job_name) or empty($filename) or 
      empty($UploadMode) or empty($folder_pk)) return;

  $job_name = pg_escape_string($job_name);
  $filename = pg_escape_string($filename);
  $desc = pg_escape_string($desc);

  $sql = "INSERT INTO upload
      (upload_desc,upload_filename,user_fk,upload_mode,upload_origin) VALUES
      ('$desc','$job_name','$user_pk','$UploadMode','$filename')";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /* get upload_pk of just added upload */
  $upload_pk = GetLastSeq("upload_upload_pk_seq", "upload");

  /* Add the upload record to the folder */
  /** Mode == 2 means child_id is upload_pk **/
  $sql = "INSERT INTO foldercontents (parent_fk,foldercontents_mode,child_id) 
               VALUES ('$folder_pk',2,'$upload_pk')";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  return ($upload_pk);
} // JobAddUpload()


/**
 * @brief Insert a new job record.
 *
 * @param $user_pk
 * @param $job_name
 * @param $upload_pk (optional)
 * @param $priority  (optional default 0)
 *
 * @return int $job_pk the job primary key
 */
function JobAddJob($user_pk, $job_name, $upload_pk=0, $priority=0)
{
  global $PG_CONN;

  $job_name = pg_escape_string($job_name);
  if (empty($upload_pk))
    $upload_pk_val = "null";
  else
    $upload_pk_val = $upload_pk;

  $sql = "INSERT INTO job
    	(job_user_fk,job_queued,job_priority,job_name,job_upload_fk) VALUES
     	('$user_pk',now(),'$priority','$job_name',$upload_pk_val)";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  $job_pk = GetLastSeq("job_job_pk_seq", "job");
  return ($job_pk);
} // JobAddJob()


/**
 * @brief Insert a jobqueue + jobdepends records.
 *
 * @param int    $job_pk the job primary key (returned by JobAddJob)
 * @param string $jq_type name of agent (should match the name in agent.conf
 * @param string $jq_args arguments to pass to the agent in the form of
 * $jq_args="folder_pk='$Folder' name='$Name' description='$Desc' ...";
 * @param string $jq_runonpfile column name
 * @param array  $Depends array of jq_pk's this jobqueue is dependent on.
 *
 * @return new jobqueue key (jobqueue.jq_pk), or null on failure
 *
 */
function JobQueueAdd($job_pk, $jq_type, $jq_args, $jq_runonpfile, $Depends)
{
  global $PG_CONN;
  $jq_args = pg_escape_string($jq_args);

  /* Make sure all dependencies exist */
  if (is_array($Depends)) 
  {
    foreach($Depends as $Dependency) 
    {
      if (empty($Dependency)) continue;

      $sql = "SELECT jq_pk FROM jobqueue WHERE jq_pk = '$Dependency'";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      $MissingDep =  (pg_num_rows($result) == 0) ? true : false;
      pg_free_result($result);

      if ($MissingDep) return;
    }
  }

  $sql = "BEGIN";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  /* Add the job */
  $sql = "INSERT INTO jobqueue ";
  $sql.= "(jq_job_fk,jq_type,jq_args,jq_runonpfile,jq_starttime,jq_endtime,jq_end_bits) VALUES ";
  $sql.= "('$job_pk','$jq_type','$jq_args',";
  if (empty($jq_runonpfile))
    $sql.= "NULL";
  else 
    $sql.= "'$jq_runonpfile'";
  $sql.= ",NULL,NULL,0);";

  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);
   
  /* Find the jobqueue that was just added */
  $jq_pk = GetLastSeq("jobqueue_jq_pk_seq", "jobqueue");
  if (empty($jq_pk))
  {
    $sql = "ROLLBACK";
    $result = pg_query($PG_CONN, $sql);
    DBCheckResult($result, $sql, __FILE__, __LINE__);
    pg_free_result($result);
    return;
  }

  /* Add dependencies */
  if (is_array($Depends)) 
  {
    foreach($Depends as $Dependency) 
    {
      if (empty($Dependency)) continue;

      $sql = "INSERT INTO jobdepends
        		(jdep_jq_fk,jdep_jq_depends_fk) VALUES
        		('$jq_pk','$Dependency')";
      $result = pg_query($PG_CONN, $sql);
      DBCheckResult($result, $sql, __FILE__, __LINE__);
      pg_free_result($result);
    }
  }

  /* Commit the jobqueue and jobdepends changes */
  $sql = "COMMIT";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  pg_free_result($result);

  return $jq_pk;
} // JobQueueAdd()


/**
 * \brief Gets the list of jobqueue records with the requested $status 
 *
 * \param string $status - the status might be:
 *        Started, Completed, Restart, Failed, Paused, etc
 *        the status 'Started' and 'Restart', you can call them as running status
 *        to get all the running job list, you can set the $status as 'tart'
 *
 * \return job list related to the jobstatus,
 *         the result is like: Array(1, 2, 3, .., i), sorted
 **/
function GetJobList($status)
{
  /* Gets the list of jobqueue records with the requested $status */
  global $PG_CONN;
	if (empty($status)) return;
  $sql = "SELECT jq_pk FROM jobqueue WHERE jq_endtext like '%$status%' order by jq_pk;";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  $job_array = array();
  $job_array =	pg_fetch_all_columns($result, 0);

	pg_free_result($result);
	return $job_array;
}

/**
 * \brief Schedule agent tasks on upload ids
 *
 * \param $upload_pk_list -  upload ids, The string can be a comma-separated list of upload ids.
 * Or, use 'ALL' to specify all upload ids.
 * \param $agent_list - array of agent plugin objects to schedule.
 * \param $Verbose - verbose output, not empty: output, empty: does not output
 */
function QueueUploadsOnAgents($upload_pk_list, $agent_list, $Verbose)
{
  global $Plugins;
  global $PG_CONN;
  global $SysConf;

  /* Get the users.default_bucketpool_fk */
  $user_pk = $SysConf['auth']['UserId'];

  if (!empty($upload_pk_list)) 
  {
    $reg_agents = array();
    $results = array();
    // Schedule them
    $agent_count = count($agent_list);
    foreach(explode(",", $upload_pk_list) as $upload_pk) 
    {
echo "bobg processing $upload_pk\n";
      if (empty($upload_pk))  continue;

      // Create a job for the upload
      // Use the upload name for the job name
      $where = "where upload_pk='$upload_pk'";
      $UploadRec = GetSingleRec("upload", $where);
      if (empty($UploadRec))
      {
        echo "ERROR: unknown upload_pk: $upload_pk\n";
        continue;
      }

      $job_name = $UploadRec["upload_filename"];
      $job_pk = JobAddJob($user_pk, $job_name, $upload_pk);
echo "bobg new job_pk: $job_pk\n";

      // don't exit on AgentAdd failure, or all the agents requested will
      // not get scheduled.
      for ($ac = 0;$ac < $agent_count;$ac++) 
      {
        $agentname = $agent_list[$ac]->URI;
        if (!empty($agentname)) 
        {
          $Agent = & $Plugins[plugin_find_id($agentname) ];
          $Dependencies = "";
          $agent_jq_pk = $Agent->AgentAdd($job_pk, $upload_pk, $ErrorMsg, $Dependencies);
echo "bobg added agent $agentname\n";
          if ($agent_jq_pk <= 0) 
          {
            echo "ERROR: Scheduling failed for Agent $agentname\n";
            echo "ERROR message: $ErrorMsg\n";
          } 
          else if ($Verbose) 
          {
            $SQL = "SELECT upload_filename FROM upload where upload_pk = $upload_pk;";
            $result = pg_query($PG_CONN, $SQL);
            DBCheckResult($result, $SQL, __FILE__, __LINE__);
            $row = pg_fetch_assoc($result);
            pg_free_result($result);
            print "$agentname is queued to run on $upload_pk:$row[upload_filename].\n";
          }
        }
      } /* for $ac */
    } /* for each $upload_pk */
  } // if $upload_pk is defined
} /* QueueUploadsOnAgents() */

/**
 * \brief Check if an agent is already scheduled in a job.
 * This is used to make sure dependencies, like unpack
 * don't get scheduled multiple times within a single job.
 *
 * \param $job_pk    - the job to be checked
 * \param $AgentName - the agent name (from agent.agent_name)
 *
 * \return 
 * jq_pk of scheduled jobqueue
 * or 0 = not scheduled
 */
function IsAlreadyScheduled($job_pk, $AgentName)
{
  global $PG_CONN;

  $jq_pk = 0;
  /* check if the upload_pk is currently in the job queue being processed */
  $sql = "SELECT jq_pk FROM jobqueue, job where job_pk=jq_job_fk AND jq_type='$AgentName' and job_pk=$job_pk";
  $result = pg_query($PG_CONN, $sql);
  DBCheckResult($result, $sql, __FILE__, __LINE__);
  if (pg_num_rows($result) > 0)
  {
    $row = pg_fetch_assoc($result);
    $jq_pk = $row["jq_pk"];
  }
  pg_free_result($result);
  return $jq_pk;
} // IsAlreadyScheduled()


/**
 * \brief Queue an agent.  This is a simple version of AgentAdd() that can be
 *  used by multiple plugins that only use upload_pk as jqargs.
 *  Before queuing, check if agent needs to be queued.  It doesn't need to be queued if:
 *  - It is already queued
 *  - It has already been run by the latest agent version
 *
 * \param $plugin caller plugin object
 * \param $job_pk
 * \param $upload_pk
 * \param $ErrorMsg - error message on failure
 * \param $Dependencies - array of named dependencies. Each array element is the plugin name.
 *         For example,  array(agent_adj2nest, agent_pkgagent).  
 *         Typically, this will just be array(agent_adj2nest).
 *
 * \returns
 * - jq_pk Successfully queued
 * -   0   Not queued, latest version of agent has previously run successfully
 * -  -1   Not queued, error, error string in $ErrorMsg
 **/
function CommonAgentAdd($plugin, $job_pk, $upload_pk, &$ErrorMsg, $Dependencies)
{
    global $PG_CONN;
    global $Plugins;
    $Deps = array();
    $DependsEmpty = array();

    /* check if the latest agent has already been run */
    if ($plugin->AgentHasResults($upload_pk) == 1) return 0;

    /* if it is already scheduled, then return success */
    if (($jq_pk = IsAlreadyScheduled($job_pk, $plugin->AgentName)) != 0 ) return $jq_pk;

    /* queue up dependencies */
    foreach ($Dependencies as $PluginName)
    {
      $DepPlugin = &$Plugins[plugin_find_id($PluginName)];
      if (!$DepPlugin)
      {
        $ErrorMsg = "Invalid plugin name: $PluginName, (CommonAgentAdd())";
        return -1;
      }
      if (($Deps[] = $DepPlugin->AgentAdd($job_pk, $upload_pk, $ErrorMsg, $DependsEmpty)) == -1)
        return -1;
    }

    /* schedule AgentName */
    $jqargs = $upload_pk;
    $jq_pk = JobQueueAdd($job_pk, $plugin->AgentName, $jqargs, "", $Deps);
    if (empty($jq_pk)){
      $ErrorMsg = _("Failed to insert agent $plugin->AgentName into job queue. jqargs: $jqargs");
      return (-1);
    }

    /* Tell the scheduler to check the queue. */
    $success  = fo_communicate_with_scheduler("database", $output, $error_msg);
    if (!$success)
    {
      $ErrorMsg = $error_msg . "\n" . $output;
      return -1;
    }

    return ($jq_pk);
} // CommonAgentAdd()
?>