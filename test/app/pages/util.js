'use strict';

var setCheckbox = function(checkboxElement, value) {
	// Ensure a checkbox element will be either checked (true) or unchecked (false), regardless of what its current value is
	checkboxElement.isSelected().then(function(checked) {
		if (checked != value) {
			checkboxElement.click();
		};
	});
};
module.exports.setCheckbox = setCheckbox;

var findDropdownByValue = function(dropdownElement, value) {
	// Returns a promise that will resolve to the <option> with the given value (as returned by optionElement.getText())
	var result = protractor.promise.defer();
	var options = dropdownElement.$$('option');
	var check = function(elem) {
		elem.getText().then(function(text) {
			if (text === value) {
				result.fulfill(elem);
			}
		});
	};
	if ("map" in options) {
		options.map(check);
	} else {
		// Sometimes we get a promise that returns a basic list; deal with that here
		options.then(function(list) {
			for (var i=0; i<list.length; i++) {
				check(list[i]);
			}
		});
	};
	return result;
};
// Need to explicitly specify exported names: see http://openmymind.net/2012/2/3/Node-Require-and-Exports/
module.exports.findDropdownByValue = findDropdownByValue;

var clickDropdownByValue = function(dropdownElement, value) {
	// Select an element of the dropdown based on its value (its text)
	var option = findDropdownByValue(dropdownElement, value);
	option.then(function(elem) {
		elem.click();
	});
};
module.exports.clickDropdownByValue = clickDropdownByValue;

// New locator to find elements that match a CSS selector, whose text (via elem.innerText in the browser) matches a regex
// Call as by.elemMatches('a', /my regular expression/)
// To get any element, call as by.elemMatches('*', /my regex/), but beware: parent elements "contain" the text of their children.
// So if your HTML is <div><span><a href="foo">xyzzy</a></span></div> and you call by.elemMatches('*', /xyzzy/),
// your locator will match three elements: the div, the span, and the a.
by.addLocator('elemMatches', function(selector, regexOrString, parentElem) {
	var searchScope = parentElem || document;
	var regex = RegExp(regexOrString);
	var allElems = searchScope.querySelectorAll(selector);
	return Array.prototype.filter.call(allElems, function(elem) {
		return regex.test(elem.innerText);
	});
});
// No need for a module.exports here, as we are adding this function to Protractor's "by" namespace

var findRowByFunc = function(repeater, searchFunc) {
	// Repeater can be either a string or an already-created by.repeater() object
	if ("string" === typeof repeater) {
		repeater = element.all(by.repeater(repeater));
	}
	var foundRow = undefined;
	var result = protractor.promise.defer();
	repeater.map(function(row) {
		row.getText().then(function(rowText) {
			if (searchFunc(rowText)) {
				foundRow = row;
			};
		});
	}).then(function() {
		if (foundRow) {
			result.fulfill(foundRow);
		} else {
			result.reject("Row not found.");
		}
	});
	return result;
};
var findRowByText = function(repeater, searchText, regExpFlags) {
	// regExpFlags is completely optional and can be left out.
	// searchText can be a string, in which case it is turned into a RegExp (with specified flags, if given),
	//      or it can be a RegExp
	// repeater is as in findRowByFunc
	if ("string" === typeof searchText) {
		searchText = new RegExp(searchText, regExpFlags);
	}
	return findRowByFunc(repeater, function(rowText) {
		return searchText.test(rowText);
	});
};
module.exports.findRowByFunc = findRowByFunc;
module.exports.findRowByText = findRowByText;

/*
 * This method is an alternative to sendKeys().  It attempts to write the textString to the value of the element instead of sending
 * one keystroke at a time
 * 
 * @param elem - ElementFinder
 * @param textString - string of text to set the value to
 */
var sendText = function(elem, textString) {
	browser.executeScript("arguments[0].value = arguments[1];", elem.find(), textString);
};
module.exports.sendText = sendText;

var waitForAlert = function(timeout) {
	if (!timeout) { timeout = 8000; }
	browser.wait(function() {
		var alertPresent = true;
		try {
			browser.switchTo().alert();
		} catch (NoSuchAlertError) {
			alertPresent = false;
		}
		return alertPresent;
	}, timeout);
};
module.exports.waitForAlert = waitForAlert;

var checkModalTextMatches = function(expectedText) {
	var modalBody = $('.modal-body');
	expect(modalBody.getText()).toMatch(expectedText);
};
module.exports.checkModalTextMatches = checkModalTextMatches;
var clickModalButton = function(btnText) {
	var modalFooter = $('.modal-footer');
	var btn = modalFooter.element(by.partialButtonText(btnText));
	btn.click();
};
module.exports.clickModalButton = clickModalButton;

// --- Email checking ---

// MailParser is from https://github.com/andris9/mailparser
var MailParser = require("mailparser").MailParser;
var exec = require('child_process').exec;

var clearMailQueue = function() {
	var promise = protractor.promise.defer();
	exec("sudo postsuper -d ALL", function() {
		promise.fulfill(true);
	});
	return promise;
};
module.exports.clearMailQueue = clearMailQueue;

var getQueuedMail = function(queueId) {
	var promise = protractor.promise.defer();
	exec("sudo postcat -bhq " + queueId, function(error, stdout, stderr) {
		var msg;
		var mailparser = new MailParser();
		mailparser.on('end', function(mailobj) {
			msg = mailobj.html || mailobj.text;
			promise.fulfill(msg);
		});
		mailparser.write(stdout);
		mailparser.end();
	});
	return promise;
};
// The getQueuedMail function is not exported, as it's internal

var queueHasMail = function() {
	var promise = protractor.promise.defer();
	exec("mailq", function(error, stdout, stderr) {
		var queueEmpty = /Mail queue is empty/.test(stdout);
		promise.fulfill(!queueEmpty);
	});
	return promise;
};
module.exports.queueHasMail = queueHasMail;

var waitForMail = function() {
	// Can't just use browser.wait(queueHasMail), because that waits for the promise to be fulfilled
	// Instead, we need to keep calling queueHasMail() until we get a true result
	return browser.driver.wait(function() {
		return queueHasMail().then(function(mailInQueue) {
			return mailInQueue;
		});
	});
};
module.exports.waitForMail = waitForMail;

var getFirstQueuedMail = function() {
	var promise = protractor.promise.defer();
	waitForMail().then(function() {
		exec("mailq", function(error, stdout, stderr) {
			// mailq outputs one header line, then a set of queue records
			// Their format is annoying to parse, but we only need the first queue ID, so it's not too bad
			var secondLine = stdout.split("\n")[1];
			var queueId = secondLine.split(/\s+/)[0];
			// Queue IDs in mailq format may have extra non-alphanum characters after them, representing a status which we don't need
			queueId = queueId.replace(/\W/, '');
			getQueuedMail(queueId).then(function(value) {
				promise.fulfill(value);
			});
		});
	});
	return promise;
};
module.exports.getFirstQueuedMail = getFirstQueuedMail;

var getFirstUrlFromMail = function() {
	// E.g., <a href="https://scriptureforge.local/auth/reset_password/fe95970f6720727fd1f578e7b9be4f64d8af8a4b">Reset Your Password</a>
	var htmlPattern = new RegExp('<a href="([^"]*)">[^<]*<\\/a>');
	var textPattern = new RegExp('^(http.*)$', 'm');
	var promise = protractor.promise.defer();
	getFirstQueuedMail().then(function(mailBody) {
		var htmlResults = htmlPattern.exec(mailBody);
		var textResults;
		if (htmlResults) {
			promise.fulfill(htmlResults[1]);
		} else {
			textResults = textPattern.exec(mailBody);
			if (textResults) {
				promise.fulfill(textResults[1]);
			} else {
				promise.fulfill(undefined);
			}
		}
	});
	return promise;
};
module.exports.getFirstUrlFromMail = getFirstUrlFromMail;
