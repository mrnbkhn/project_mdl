# VPL Question type
Version: 1.2.6 (November 2019)

Authors:
- Astor Bizard
- Florent Paccalet (previous versions).

This software is part of the Caseine project.  
This software was developped with the support of the following organizations:
- Université Grenoble Alpes
- Institut Polytechnique de Grenoble

## Introduction

VPL Questions are questions that can fit within a Moodle quiz. They are intended to create small to medium coding exercises, based on the [Virtual Programming Lab plugin](https://moodle.org/plugins/mod_vpl).  
They are designed to offer a simple interface for students, while keeping the power and versatility of Virtual Programming Labs.

For example:  
![VPL Question example: Hello World](metadata/screenshots/student_view.png)


## VPL Questions Architecture

A picture is worth a thousand words, so here is how it works (you can also read the thousand words right below the picture!):

![VPL Question architecture](metadata/vplquestion_architecture.png)

A VPL Question is (as its name says) based on a VPL (Virtual Programming Lab).  
The execution and evaluation of the question takes place inside that VPL, which allows powerful customizations.  
A single VPL can be used for several questions, as long as it is located in the same course.

In order to execute and evaluate student's code in the VPL, the question sends that code (injected within the required file, at the place of the *{{ANSWER}}* tag) in a new VPL submission. The submission also includes question-specific execution files. The VPL then replaces its own execution files by the ones present in the submission.  
This is what allows several questions to be based on the same VPL: as the execution files are question-specific, different execution behaviors (e.g. different test cases) can be achieved on the same VPL. However, this can not be achieved for standard execution scripts.

## Create a VPL Question

### Step 1: Create a dedicated VPL

The first step to create a VPL Question is to create the VPL it will be using.  
To do so, simply create a new VPL inside your course.  
It is (strongly) recommended to create it inside a hidden section of the course, and make it available. The goal is to hide it from students, while still allowing them to make submissions to it.

When the VPL is created, there are a few settings you will have to change before it can be used:
- *Edit settings > Submission period*: It is recommended to disable the deadline (Due date).
- *Edit settings > Submission restrictions*: Set the Maximum number of files to a large enough number, as submissions will include execution files.
- *Edit settings > Common module settings*: If you put the VPL inside a hidden section, make sure the Availability is set to *Available but not shown on course page*.
- *Execution options > Common module settings*: Set Run, Evaluate and Automatic grade to *Yes*.
- *Execution files*: Create a file named *pre_vpl_run.sh*, and paste the following code in it (this is the code that deals with submitted execution files):  

```bash
for qvplfile in `ls -1`
do
     file=${qvplfile%_qvpl}
     if [ -x $file ]; then executable=true; else executable=false; fi
     test "$qvplfile" != "$file" && mv "$qvplfile" "$file"
     if $executable; then chmod u+x $file; fi
done
```
- *Required files*: Create one (and only one) required file. You can write some basic code in it (like class declaration in java, an empty main function, ...).

You can also edit/add execution files. Please note that:
- Standard execution scripts will not be editable per-question. Please edit them from the VPL.
- Other execution files will be editable per-question. However, please create them first in the VPL (you won't be able to create them from a question).

**IMPORTANT:** Students submissions on this VPL will be discarded by the VPL Question (this behavior can be changed by an administrator in this plugin settings). This is why it is important to create a dedicated VPL (and not to use a VPL where students may want to retrieve their previous submissions).

### Step 2: Create one or more VPL Questions

Once a dedicated is created by following the instructions above, you are now ready to create your VPL Questions !  
To do so, create a question either from a Quiz or a Question bank, and select *VPL Question*:  
![VPL Question type select](metadata/screenshots/select_vplquestion.png)

Let's breakdown what you can do in the question editing form:
- *General*: You fill find here all standard settings for a question, like question name and description.
- *VPL Question template*: Here you can select the VPL you created earlier. This will allow you to edit the required file. Please make sure that it includes the **{{ANSWER}}** tag: this is where the student's code will be injected. Also, please note:
	- Code written here will **not** be visible by students.
	- The changes you make to the required file will only be effective for the current question.
![Required file template edition](metadata/screenshots/reqfile_edit.png)
- *Answer template*: Here you can edit what will be pre-filled in the student's answer box. It can be left empty.
- *Teacher correction*: Please write here your correction for the question. This is what will be used as the "Correct answer" for Quiz feedback.  
	If *Validate* is checked, the provided answer will be checked against test cases upon question save. This is very useful to check everything is working fine on VPL side, and that both your correction and your tests are correct.
- *Execution files and evaluate settings*: This is where you can edit the execution and evaluation behavior of the question. Once again, all modifications done here will only affect the current question.
	- You can edit execution files (most commonly *vpl_evaluate.cases*). If there is an execution file that you created in the VPL but you do not need in this question, write *UNUSED* at the beginning of the file: it will be ignored.  
	Note: Execution files are only sent to the VPL upon question evaluation. If you need them during execution (Run), please set them as *Files to keep when running* in the VPL.
	- You can also change the behavior of the *Pre-check* button (make it act as the *Debug* button on the VPL, use the execution files above, or use other files). In the last case, you will be able to edit another set of execution files, this time specific to the *Pre-check* button.
	- You can finally decide whether students need to achieve perfect VPL grade to get the question correct, or use relative VPL grade to get a proportional note on the question.

## Known issues

- *Evaluation failures*  
	It happens that the VPL on which a VPL Question is based fails to evaluate the submission. This failure results in no grade being obtained at the end of that evaluation. VPL Questions are designed to retry several times in that case, however after some retries we have to declare that the grade for this question submission is 0. Please be aware that this can happen even on right answers, so please consider reviewing evaluation results in the case VPL Questions are used in any sort of graded assignment.
- *Test cases hacking*  
	Test cases, along with other execution files specific to a question, are sent to the VPL as submission files. Even if the VPL is hidden, it has to be available to students so that they can submit to it. This leads to a possibility for students to find access to the VPL, thus to the execution files they submitted. Please be aware of that possibility - what you decide to do about it is up to you.