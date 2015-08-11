/*
 * @package /Applications/Adobe InDesign CC 2014/Scripts/Scripts Panel/Chaucer/FixedLayoutPreflight.jsx
 *
 * @description This script should be run on any InDesign document that will be imported into Chaucer as a
 *              fixed layout project. It will "unthread" all text frames. After running this script, export
 *              the document to an IDML file using the InDesign "Package..." menuitem.
 *
 * @copyright  Copyright © 2014 Metrodigi, Inc.
 *          All Rights Reserved.
 *          Unpublished rights reserved under the copyright laws of the United States.
 */

main();

function main(){
    //Make certain that user interaction (display of dialogs, etc.) is turned on.
    app.scriptPreferences.userInteractionLevel = UserInteractionLevels.interactWithAll;

    var a = app.activeDocument;
    var numThreadedStories = 0;
    var numSingleStories = 0;
    var storyIDs = [];

    for (var i = 0; i < a.stories.length; i++)
    {
        var story = a.stories[i];
        storyIDs.push(story.id);
    }

    // for every story *currently* in the document
    for (var i = 0; i < storyIDs.length; i++)
    {
        var id = storyIDs[i];
        var story = a.stories.itemByID(id);

        if (story.textContainers.length > 1)
        {
            numThreadedStories++;
            duplicateFrames(story);
            removeAllFrames(story);
        }
        else
        {
            numSingleStories++;
        }
    }

    if (a.stories.length == numSingleStories)
    {
        var result = "Finished\nThis document contains " + a.stories.length + " unthreaded stories and 0 threaded stories.\n\nIt is ready for export as a fixed layout IDML file.";
        alert(result);
    }
    else
    {
        var newNumStories = a.stories.length - numSingleStories;
        var result = "Finished\nThis document contained " + numThreadedStories.toString() + " stories that were threaded. They have been split into " + newNumStories.toString() + " separate text frames.\n\nIt is ready for export as a fixed layout IDML file.";
        alert(result);
    }

    return;
}

function duplicateFrames(story)
{
    var myTextFrame;
    for (var i = story.textContainers.length-1; i >= 0; i--)
    {
        myTextFrame = story.textContainers[i];
        myTextFrame.duplicate();
    }
}

function removeAllFrames(story)
{
    for (var i = story.textContainers.length-1; i >= 0; i--)
    {
        story.textContainers[i].remove();
    }
}
