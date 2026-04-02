/*!
* Authentication form animations from Darin Senneff (https://www.darins.page/)
* Copyright 2018 Darin Senneff
*/
import {Controller} from '@hotwired/stimulus';
import {gsap} from 'gsap';
import {MorphSVGPlugin} from 'gsap/MorphSVGPlugin';

export default class extends Controller {

    static targets = [
        'email',
        'password',
        'spy',
        'bear',
        'twoFingers',
        'armL',
        'armR',
        'eyeL',
        'eyeR',
        'nose',
        'mouth',
        'mouthBG',
        'mouthSmallBG',
        'mouthMediumBG',
        'mouthLargeBG',
        'mouthMaskPath',
        'mouthOutline',
        'tooth',
        'tongue',
        'chin',
        'face',
        'eyeBrow',
        'outerEarL',
        'outerEarR',
        'earHairL',
        'earHairR',
        'hair',
        'bodyBG',
        'bodyBGchanged'
    ];

    activeElement;
    curEmailIndex;
    screenCenter;
    svgCoords;
    emailCoords;
    emailScrollMax;
    chinMin = .5;
    dFromC;
    mouthStatus = "small";
    blinking;
    eyeScale = 1;
    eyesCovered = false;
    eyeLCoords;
    eyeRCoords
    noseCoords;
    mouthCoords;
    eyeLAngle;
    eyeLX;
    eyeLY;
    eyeRAngle;
    eyeRX;
    eyeRY;
    noseAngle;
    noseX;
    noseY;
    mouthAngle;
    mouthX;
    mouthY;
    mouthR;
    chinX;
    chinY;
    chinS;
    faceX;
    faceY;
    faceSkew;
    eyebrowSkew;
    outerEarX;
    outerEarY;
    hairX;
    hairS;

    constructor(...args) {
        super(...args);
        gsap.registerPlugin(MorphSVGPlugin);
    }

    calculateFaceMove(e) {
        var
            carPos = this.emailTarget.selectionEnd,
            div = document.createElement('div'),
            span = document.createElement('span'),
            copyStyle = getComputedStyle(this.emailTarget),
            caretCoords = {}
        ;
        if(carPos == null || carPos == 0) {
            // if browser doesn't support 'selectionEnd' property on input[type="email"], use 'value.length' property instead
            carPos = this.emailTarget.value.length;
        }
        [].forEach.call(copyStyle, function(prop){
            div.style[prop] = copyStyle[prop];
        });
        div.style.position = 'absolute';
        document.body.appendChild(div);
        div.textContent = this.emailTarget.value.substr(0, carPos);
        span.textContent = this.emailTarget.value.substr(carPos) || '.';
        div.appendChild(span);

        if(this.emailTarget.scrollWidth <= this.emailScrollMax) {
            caretCoords = this.getPosition(span);
            this.dFromC = this.screenCenter - (caretCoords.x + this.emailCoords.x);
            this.eyeLAngle = this.getAngle(this.eyeLCoords.x, this.eyeLCoords.y, this.emailCoords.x + caretCoords.x, this.emailCoords.y + 25);
            this.eyeRAngle = this.getAngle(this.eyeRCoords.x, this.eyeRCoords.y, this.emailCoords.x + caretCoords.x, this.emailCoords.y + 25);
            this.noseAngle = this.getAngle(this.noseCoords.x, this.noseCoords.y, this.emailCoords.x + caretCoords.x, this.emailCoords.y + 25);
            this.mouthAngle = this.getAngle(this.mouthCoords.x, this.mouthCoords.y, this.emailCoords.x + caretCoords.x, this.emailCoords.y + 25);
        } else {
            this.eyeLAngle = this.getAngle(this.eyeLCoords.x, this.eyeLCoords.y, this.emailCoords.x + this.emailScrollMax, this.emailCoords.y + 25);
            this.eyeRAngle = this.getAngle(this.eyeRCoords.x, this.eyeRCoords.y, this.emailCoords.x + this.emailScrollMax, this.emailCoords.y + 25);
            this.noseAngle = this.getAngle(this.noseCoords.x, this.noseCoords.y, this.emailCoords.x + this.emailScrollMax, this.emailCoords.y + 25);
            this.mouthAngle = this.getAngle(this.mouthCoords.x, this.mouthCoords.y, this.emailCoords.x + this.emailScrollMax, this.emailCoords.y + 25);
        }

        this.eyeLX = Math.cos(this.eyeLAngle) * 20;
        this.eyeLY = Math.sin(this.eyeLAngle) * 10;
        this.eyeRX = Math.cos(this.eyeRAngle) * 20;
        this.eyeRY = Math.sin(this.eyeRAngle) * 10;
        this.noseX = Math.cos(this.noseAngle) * 23;
        this.noseY = Math.sin(this.noseAngle) * 10;
        this.mouthX = Math.cos(this.mouthAngle) * 23;
        this.mouthY = Math.sin(this.mouthAngle) * 10;
        this.mouthR = Math.cos(this.mouthAngle) * 6;
        this.chinX = this.mouthX * .8;
        this.chinY = this.mouthY * .5;
        this.chinS = 1 - ((this.dFromC * .15) / 100);
        if(this.chinS > 1) {
            this.chinS = 1 - (this.chinS - 1);
            if(this.chinS < this.chinMin) {
                this.chinS = this.chinMin;
            }
        }
        this.faceX = this.mouthX * .3;
        this.faceY = this.mouthY * .4;
        this.faceSkew = Math.cos(this.mouthAngle) * 5;
        this.eyebrowSkew = Math.cos(this.mouthAngle) * 25;
        this.outerEarX = Math.cos(this.mouthAngle) * 4;
        this.outerEarY = Math.cos(this.mouthAngle) * 5;
        this.hairX = Math.cos(this.mouthAngle) * 6;
        this.hairS = 1.2;

        gsap.to(this.eyeLTarget, 1, {x: -this.eyeLX , y: -this.eyeLY, ease: "expo.out"});
        gsap.to(this.eyeRTarget, 1, {x: -this.eyeRX , y: -this.eyeRY, ease: "expo.out"});
        gsap.to(this.noseTarget, 1, {x: -this.noseX, y: -this.noseY, rotation: this.mouthR, transformOrigin: "center center", ease: "expo.out"});
        gsap.to(this.mouthTarget, 1, {x: -this.mouthX , y: -this.mouthY, rotation: this.mouthR, transformOrigin: "center center", ease: "expo.out"});
        gsap.to(this.chinTarget, 1, {x: -this.chinX, y: -this.chinY, scaleY: this.chinS, ease: "expo.out"});
        gsap.to(this.faceTarget, 1, {x: -this.faceX, y: -this.faceY, skewX: -this.faceSkew, transformOrigin: "center top", ease: "expo.out"});
        gsap.to(this.eyeBrowTarget, 1, {x: -this.faceX, y: -this.faceY, skewX: -this.eyebrowSkew, transformOrigin: "center top", ease: "expo.out"});
        gsap.to(this.outerEarLTarget, 1, {x: this.outerEarX, y: -this.outerEarY, ease: "expo.out"});
        gsap.to(this.outerEarRTarget, 1, {x: this.outerEarX, y: this.outerEarY, ease: "expo.out"});
        gsap.to(this.earHairLTarget, 1, {x: -this.outerEarX, y: -this.outerEarY, ease: "expo.out"});
        gsap.to(this.earHairRTarget, 1, {x: -this.outerEarX, y: this.outerEarY, ease: "expo.out"});
        gsap.to(this.hairTarget, 1, {x: this.hairX, scaleY: this.hairS, transformOrigin: "center bottom", ease: "expo.out"});

        document.body.removeChild(div);
    };

    onEmailInput(e) {
        this.calculateFaceMove(e);
        var value = this.emailTarget.value;
        this.curEmailIndex = value.length;

        // very crude email validation to trigger effects
        if(this.curEmailIndex > 0) {
            if(this.mouthStatus == "small") {
                this.mouthStatus = "medium";
                gsap.to([this.mouthBGTarget, this.mouthOutlineTarget, this.mouthMaskPathTarget], 1, {morphSVG: this.mouthMediumBGTarget, ease: "expo.out"});
                gsap.to(this.toothTarget, 1, {x: 0, y: 0, ease: "expo.out"});
                gsap.to(this.tongueTarget, 1, {x: 0, y: 1, ease: "expo.out"});
                gsap.to([this.eyeLTarget, this.eyeRTarget], 1, {scaleX: .85, scaleY: .85, ease: "expo.out"});
                this.eyeScale = .85;
            }
            if(value.includes("@")) {
                this.mouthStatus = "large";
                gsap.to([this.mouthBGTarget, this.mouthOutlineTarget, this.mouthMaskPathTarget], 1, {morphSVG: this.mouthLargeBGTarget, ease: "expo.out"});
                gsap.to(this.toothTarget, 1, {x: 3, y: -2, ease: "expo.out"});
                gsap.to(this.tongueTarget, 1, {y: 2, ease: "expo.out"});
                gsap.to([this.eyeLTarget, this.eyeRTarget], 1, {scaleX: .65, scaleY: .65, ease: "expo.out", transformOrigin: "center center"});
                this.eyeScale = .65;
            } else {
                this.mouthStatus = "medium";
                gsap.to([this.mouthBGTarget, this.mouthOutlineTarget, this.mouthMaskPathTarget], 1, {morphSVG: this.mouthMediumBGTarget, ease: "expo.out"});
                gsap.to(this.toothTarget, 1, {x: 0, y: 0, ease: "expo.out"});
                gsap.to(this.tongueTarget, 1, {x: 0, y: 1, ease: "expo.out"});
                gsap.to([this.eyeLTarget, this.eyeRTarget], 1, {scaleX: .85, scaleY: .85, ease: "expo.out"});
                this.eyeScale = .85;
            }
        } else {
            this.mouthStatus = "small";
            gsap.to([this.mouthBGTarget, this.mouthOutlineTarget, this.mouthMaskPathTarget], 1, {morphSVG: this.mouthSmallBGTarget, ease: "expo.out"});
            gsap.to(this.toothTarget, 1, {x: 0, y: 0, ease: "expo.out"});
            gsap.to(this.tongueTarget, 1, {y: 0, ease: "expo.out"});
            gsap.to([this.eyeLTarget, this.eyeRTarget], 1, {scaleX: 1, scaleY: 1, ease: "expo.out"});
            this.eyeScale = 1;
        }
    }

    onEmailFocus(e) {
        this.activeElement = "email";
        //this.stopBlinking();
        //this.calculateFaceMove();
        this.onEmailInput();
    }

    onEmailBlur(e) {
        this.activeElement = null;
        setTimeout(() => {
            if(this.activeElement == "email") {
            } else {
                //this.startBlinking();
                this.resetFace();
            }
        }, 100);
    }

    onPasswordFocus(e) {
        this.activeElement = "password";
        if(!this.eyesCovered) {
            this.coverEyes();
        }
    }

    onPasswordBlur(e) {
        this.activeElement = null;
        setTimeout(() => {
            if(this.activeElement == "toggle" || this.activeElement == "password") {
            } else {
                this.uncoverEyes();
            }
        }, 100);
    }

    onPasswordToggleFocus(e) {
        this.activeElement = "toggle";
        if(!this.eyesCovered) {
            this.coverEyes();
        }
    }

    onPasswordToggleBlur(e) {
        this.activeElement = null;
        setTimeout(() => {
            if(this.activeElement == "password" || this.activeElement == "toggle") {
            } else {
                this.uncoverEyes();
            }
        }, 100);
    }

    onPasswordToggleClick(e) {
        e.target.focus();
        setTimeout(() => {
            if(e.target.getAttribute("aria-pressed") == "true") {
                for(var input of this.passwordTargets) {
                    input.type = "text";
                }
                this.spreadFingers();
            } else {
                for(var input of this.passwordTargets) {
                    input.type = "password";
                }
                this.closeFingers();
            }
        }, 100);
    }

    spreadFingers() {
        gsap.to(this.twoFingersTarget, .35, {transformOrigin: "bottom left", rotation: 30, x: -9, y: -2, ease: "power2.inOut"});
    }

    closeFingers() {
        gsap.to(this.twoFingersTarget, .35, {transformOrigin: "bottom left", rotation: 0, x: 0, y: 0, ease: "power2.inOut"});
    }

    coverEyes() {
        gsap.killTweensOf([this.armLTarget, this.armRTarget]);
        gsap.set([this.armLTarget, this.armRTarget], {visibility: "visible"});
        gsap.to(this.armLTarget, .45, {x: -93, y: 10, rotation: 0, ease: "power4.out"});
        gsap.to(this.armRTarget, .45, {x: -93, y: 10, rotation: 0, ease: "power4.out", delay: .1});
        gsap.to(this.bodyBGTarget, .45, {morphSVG: this.bodyBGchangedTarget, ease: "power4.out"});
        this.eyesCovered = true;
    }

    uncoverEyes() {
        gsap.killTweensOf([this.armLTarget, this.armRTarget]);
        gsap.to(this.armLTarget, 1.35, {y: 220, ease: "power4.out"});
        gsap.to(this.armLTarget, 1.35, {rotation: 105, ease: "power4.out", delay: .1});
        gsap.to(this.armRTarget, 1.35, {y: 220, ease: "power4.out"});
        gsap.to(this.armRTarget, 1.35, {rotation: -105, ease: "power4.out", delay: .1, onComplete: () => gsap.set([this.armLTarget, this.armRTarget], {visibility: "hidden"})});
        gsap.to(this.bodyBGTarget, .45, {morphSVG: this.bodyBGTarget, ease: "power4.out"});
        this.eyesCovered = false;
    }

    resetFace() {
        gsap.to([this.eyeLTarget, this.eyeRTarget], 1, {x: 0, y: 0, ease: "expo.out"});
        gsap.to(this.noseTarget, 1, {x: 0, y: 0, scaleX: 1, scaleY: 1, ease: "expo.out"});
        gsap.to(this.mouthTarget, 1, {x: 0, y: 0, rotation: 0, ease: "expo.out"});
        gsap.to(this.chinTarget, 1, {x: 0, y: 0, scaleY: 1, ease: "expo.out"});
        gsap.to([this.faceTarget, this.eyeBrowTarget], 1, {x: 0, y: 0, skewX: 0, ease: "expo.out"});
        gsap.to([this.outerEarLTarget, this.outerEarRTarget, this.earHairLTarget, this.earHairRTarget, this.hairTarget], 1, {x: 0, y: 0, scaleY: 1, ease: "expo.out"});
    }

    startBlinking(delay) {
        if(delay) {
            delay = this.getRandomInt(delay);
        } else {
            delay = 1;
        }
        this.blinking = gsap.to([this.eyeLTarget, this.eyeRTarget], .1, {delay: delay, scaleY: 0, yoyo: true, repeat: 1, transformOrigin: "center center", onComplete: () => this.startBlinking(12)});
    }

    stopBlinking() {
        this.blinking.kill();
        this.blinking = null;
        gsap.set([this.eyeLTarget, this.eyeRTarget], {scaleY: this.eyeScale});
    }

    getRandomInt(max) {
        return Math.floor(Math.random() * Math.floor(max));
    }

    getAngle(x1, y1, x2, y2) {
        var angle = Math.atan2(y1 - y2, x1 - x2);
        return angle;
    }

    getPosition(el) {
        var xPos = 0;
        var yPos = 0;

        while (el) {
            if (el.tagName == "BODY") {
                // deal with browser quirks with body/window/document and page scroll
                var xScroll = el.scrollLeft || document.documentElement.scrollLeft;
                var yScroll = el.scrollTop || document.documentElement.scrollTop;

                xPos += (el.offsetLeft - xScroll + el.clientLeft);
                yPos += (el.offsetTop - yScroll + el.clientTop);
            } else {
                // for all other non-BODY elements
                xPos += (el.offsetLeft - el.scrollLeft + el.clientLeft);
                yPos += (el.offsetTop - el.scrollTop + el.clientTop);
            }

            el = el.offsetParent;
        }
        return {
            x: xPos,
            y: yPos
        };
    }

    initForm() {
        // some measurements for the svg's elements
        this.svgCoords = this.getPosition(this.bearTarget);
        this.emailCoords = this.getPosition(this.emailTarget);
        this.screenCenter = this.svgCoords.x + (this.bearTarget.offsetWidth / 2);
        this.eyeLCoords = {x: this.svgCoords.x + 84, y: this.svgCoords.y + 76};
        this.eyeRCoords = {x: this.svgCoords.x + 113, y: this.svgCoords.y + 76};
        this.noseCoords = {x: this.svgCoords.x + 97, y: this.svgCoords.y + 81};
        this.mouthCoords = {x: this.svgCoords.x + 100, y: this.svgCoords.y + 100};

        // move arms to initial positions
        gsap.set(this.armLTarget, {x: -93, y: 220, rotation: 105, transformOrigin: "top left"});
        gsap.set(this.armRTarget, {x: -93, y: 220, rotation: -105, transformOrigin: "top right"});

        // set initial mouth property (fixes positioning bug)
        gsap.set(this.mouthTarget, {transformOrigin: "center center"});

        // activate blinking
        this.startBlinking(5);

        // determine how far email input can go before scrolling occurs
        // will be used as the furthest point avatar will look to the right
        this.emailScrollMax = this.emailTarget.scrollWidth;

        // clear the console
        //console.clear();
    }

    connect() {
        this.initForm();
    }
}
