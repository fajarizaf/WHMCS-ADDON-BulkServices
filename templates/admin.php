<script type='text/javascript' src='../modules/addons/bulkservices/templates/js/action.js'></script>

<ul class="nav nav-tabs">
  <li role="home" class="active"><a data-toggle="tab" href="#home"><b>Upload</b></a></li>
  <li role="log"><a data-toggle="tab" href="#log"><b>Document Logs</b></a></li>
</ul>


<div class="tab-content">
  <div id="home" class="tab-pane fade in active">
    <div class="page-header">
        <h2>Upload Service Data to be Executed <small>Supported File Extensions (.xlsx)</small></h2>
        <div style="display:none" class="alert alert-success">No Messages</div>
        <div style="display:none" class="alert alert-danger">No Messages</div>
    </div>

    <form method="POST" id="formservices" action="<?php print $modulelink; ?>" enctype='multipart/form-data' class="form-inline">
        <div class="form-group">
            <label>Choose File</label>
            <input type="file" name="file" class="form-control" id="file" placeholder="upload">
        </div>
        <div class="form-group">
            <label>Action</label>
            <select id="proses" class="form-control" name='proses'>
                <option value=''>No Action</option>
                <option value='Cancel'>Cancel Invoice</option>
                <option value='Suspend'>Suspend</option>
                <option value='Terminate'>Terminate</option>
            </select>
        </div>
        <input type="submit" id="btnprocess" value="process" class="btn btn-warning" />
    </form>
    <br/>
    <div class="progress"> 
      <div class="progress-bar progress-bar-warning progress-bar-striped" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"></div> 
    </div>

    <div class='monitor'></div>

    <div class='remaining' style='display:none'>
        <div class='row'>
          <div class='col-md-12'><h3 style='color:#fff;margin:0px;'><span class="label label-primary" style='background-color:#053661;color:#fff'>Total Services : <span class='total'>>345</span></span> -> Total <span class='totalremain'>124</span> Services Remaining</h3></div>
        </div>
    </div>

    <div class='result' style='display:none'>
        <div class='row'>
          <div class='col-md-6'><h3 style='color:#fff;margin:0px;padding-top:8px;'><b>Bulk Process Complete</b> > success : <span class='suk'>0</span> failed : <span class='fal'>0</span></h3></div>
          <div class='col-md-6'><a class='linkreport' href="#"><button style='float:right;margin-right:10px;color:#04190a' class='btn btn-success'>Download Report</button></a></div>
        </div>
    </div>
    
  </div>
  <div id="log" class="tab-pane fade">

    <div class="page-header">
        <h2>All bulk processing will be recorded into documents</h2>
        <div style="display:none" class="alert alert-success">No Messages</div>
    </div>

    <table class="datatable" style="width:100%">
    <tbody>
      <tr>
        <th>Document Name</th>
        <th>Last Update</th>
        <th>Action</th>
        <th>Failed</th>
        <th>Success</th>
        <th>File</th>
      <tr>
      <?php if(count($report)) { ?>
        <?php foreach($report as $row) { ?>
          <tr>
            <td><?php echo $row->docname; ?></td>
            <td><?php echo $row->created_at; ?></td>
            <td style='text-align:center'><?php echo printLabel($row->proses); ?></td>
            <td style='text-align:center'><?php echo $row->failed; ?></td>
            <td style='text-align:center'><?php echo $row->success; ?></td>
            <td style='text-align:center'><a href="<?php echo $system_url; ?>downloads/bulkservices/<?php echo $row->docname; ?>"><button type="button" class="btn btn-default">Download</button></td>
          </tr>
        <?php } ?>
      <?php } ?>
    </tbody>
    </table>
  </div>
</div>

<?php

function printLabel($status) {

  if($status == 'Terminate') {
    return '<span class="label label-danger">Terminate</span>';
  }
  if($status == 'Suspend') {
      return '<span class="label label-warning">Suspend</span>';
  }
  if($status == 'Cancel') {
      return '<span class="label label-success">Cancel</span>';
  }

}

?>


<style type='text/css'>

.remaining {
  padding-top:10px;
  padding-left:10px;
  padding-bottom:15px;
  background-color:#0275d8;
  color:#fff;
}

.result {
  padding-top:5px;
  padding-left:10px;
  padding-bottom:5px;
  background-color:#188038;
  color:#fff;
}

.monitor {
  width:100%;
  height:400px;
  background:#1f1f1f;
  color:#efefef;
  overflow-y:scroll;
  padding:10px;
}

div h1 {
  z-index:1;
  position: relative;
}
div h1::before {
  content: url("https://img.shields.io/badge/-v1.1.100-orange.svg?colorA=darkgray");
  z-index: -1;
  top: .125em;
  left: 170px;
  position: absolute;  
}
</style>      

